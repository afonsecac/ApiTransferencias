# A1 (COMR-96): medición definitiva, con el criterio corregido (nombre sin prefijo y nombres alternativos).
# Ejecutar antes a1_geocodificacion_prueba_inicial.py, que genera a1_direcciones.json.
import json, re, unicodedata, urllib.parse, urllib.request, collections
NOM='http://localhost:18081'
rows=json.load(open(__import__('pathlib').Path(__file__).with_name('a1_direcciones.json')))
def norm(s): return re.sub(r'[^a-z0-9 ]','',unicodedata.normalize('NFKD',(s or '').lower()).encode('ascii','ignore').decode()).strip()
def q(text):
    url=NOM+'/search?'+urllib.parse.urlencode({'q':text,'format':'jsonv2','addressdetails':1,'limit':1,'accept-language':'es'})
    r=json.load(urllib.request.urlopen(url,timeout=30)); return r[0] if r else None
def core(calle):  # nombre sin prefijo genérico, salvo calles numeradas/letradas (Calle 23, Calle L)
    m=re.match(r'^(Calle|Avenida|Calzada de|Calzada)\s+(.+)$',calle,flags=re.I)
    if not m: return calle
    rest=m.group(2)
    return calle if re.fullmatch(r'[0-9A-Za-z]{1,3}',rest) else rest
def clasif(row,res):
    if not res: return 'sin_resultado'
    a=res.get('address',{}); dn=norm(res.get('display_name',''))
    blob=norm(' '.join(str(v) for v in a.values()))+' '+dn
    prov=norm(row['prov']) in blob
    zona=norm(row['mun']) in blob or norm(row['barrio']) in blob
    calle=norm(core(row['calle']))
    es_via=res.get('category')=='highway'
    calle_ok=es_via and calle and calle in dn
    if calle_ok and prov and zona: return 'calle_y_zona_correctas'
    if calle_ok and prov: return 'calle_correcta_zona_dudosa'
    if prov and zona: return 'zona_correcta_calle_no'
    if prov: return 'solo_provincia'
    return 'erronea'
formas={'A: calle+municipio+provincia (con prefijo, como la dirección)':lambda r:f"{r['calle']}, {r['mun']}, {r['prov']}, Cuba",
        'B: nombre sin prefijo+municipio+provincia':lambda r:f"{core(r['calle'])}, {r['mun']}, {r['prov']}, Cuba",
        'C: nombre sin prefijo+provincia':lambda r:f"{core(r['calle'])}, {r['prov']}, Cuba"}
out={}
for nombre,f in formas.items():
    cnt=collections.Counter(); por=collections.defaultdict(lambda:[0,0])
    for r in rows:
        c=clasif(r,q(f(r))); cnt[c]+=1; por[r['prov']][1]+=1
        if c=='calle_y_zona_correctas' or c=='calle_correcta_zona_dudosa': por[r['prov']][0]+=1
    out[nombre]={'conteo':dict(cnt),'calle_encontrada':f"{cnt['calle_y_zona_correctas']+cnt['calle_correcta_zona_dudosa']}/100",'zona_o_mejor':f"{cnt['calle_y_zona_correctas']+cnt['calle_correcta_zona_dudosa']+cnt['zona_correcta_calle_no']}/100",'por_provincia':{k:f"{v[0]}/{v[1]}" for k,v in por.items()}}
print(json.dumps(out,ensure_ascii=False,indent=1))
