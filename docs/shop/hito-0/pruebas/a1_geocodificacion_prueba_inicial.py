# A1 (COMR-96): primera medición (consulta con la dirección completa vs simplificada vs estructurada).
# Requiere Nominatim con el extracto de Cuba en http://localhost:18081 (ver docs/shop/hito-0/a1-geocodificacion.md).
import json, random, re, time, unicodedata, urllib.parse, urllib.request
random.seed(7)
NOM='http://localhost:18081'
def norm(s): return re.sub(r'[^a-z0-9 ]','',unicodedata.normalize('NFKD',(s or '').lower()).encode('ascii','ignore').decode()).strip()
# (calle, entre1, entre2, barrio, municipio, provincia, ciudad) — direcciones verosímiles, SIN datos personales
H='La Habana'
L=[
('Calle 23','10','12','Vedado','Plaza de la Revolución',H),('Calle 12','19','21','Vedado','Plaza de la Revolución',H),('Calle L','23','25','Vedado','Plaza de la Revolución',H),
('Calle G','13','15','Vedado','Plaza de la Revolución',H),('Línea','A','B','Vedado','Plaza de la Revolución',H),('Calle 17','H','I','Vedado','Plaza de la Revolución',H),
('Calle 21','K','L','Vedado','Plaza de la Revolución',H),('Paseo','13','15','Vedado','Plaza de la Revolución',H),('Calle 25','H','I','Vedado','Plaza de la Revolución',H),('Calle N','21','23','Vedado','Plaza de la Revolución',H),
('Obispo','Mercaderes','San Ignacio','Habana Vieja','La Habana Vieja',H),('Obrapía','Mercaderes','Oficios','Habana Vieja','La Habana Vieja',H),('O\'Reilly','Tacón','Cuba','Habana Vieja','La Habana Vieja',H),
('Teniente Rey','Bernaza','Villegas','Habana Vieja','La Habana Vieja',H),('Compostela','Muralla','Teniente Rey','Habana Vieja','La Habana Vieja',H),('Calle Cuba','Obrapía','Lamparilla','Habana Vieja','La Habana Vieja',H),
('Neptuno','Escobar','Lealtad','Centro Habana','Centro Habana',H),('San Lázaro','Belascoaín','Gervasio','Centro Habana','Centro Habana',H),('Galiano','Concordia','Virtudes','Centro Habana','Centro Habana',H),
('Reina','Rayo','Ángeles','Centro Habana','Centro Habana',H),('Infanta','San Martín','Carlos III','Centro Habana','Centro Habana',H),('Zanja','Escobar','Lealtad','Centro Habana','Centro Habana',H),
('Quinta Avenida','72','74','Miramar','Playa',H),('Calle 70','35','41','Miramar','Playa',H),('Calle 30','15','17','Miramar','Playa',H),('Avenida 3ra','60','62','Miramar','Playa',H),('Calle 84','5ta','7ma','Miramar','Playa',H),
('Calzada de Diez de Octubre','Santa Catalina','Luco','Lawton','Diez de Octubre',H),('Calzada de Jesús del Monte','Lawton','Santa Catalina','Jesús del Monte','Diez de Octubre',H),('Calle Luco','Diez de Octubre','Concepción','Lawton','Diez de Octubre',H),
('Calzada del Cerro','Palatino','Tulipán','El Cerro','Cerro',H),('Calle Palatino','Calzada del Cerro','Auditor','El Cerro','Cerro',H),('Calzada de Cerro','Infanta','Ayestarán','El Cerro','Cerro',H),
('Calle 51','110','114','Marianao','Marianao',H),('Calle 100','51','53','Marianao','Marianao',H),('Avenida 51','100','102','Marianao','Marianao',H),
('Avenida Rancho Boyeros','Boyeros','Camagüey','Boyeros','Boyeros',H),('Calle Santa Catalina','Boyeros','Lombillo','Boyeros','Boyeros',H),('Calzada de Managua','Arroyo Naranjo','Mantilla','Mantilla','Arroyo Naranjo',H),
('Calzada de Güines','Concejal Veiga','Luyanó','San Miguel del Padrón','San Miguel del Padrón',H),('Calle 234','31','33','La Lisa','La Lisa',H),('Calle 222','31','33','La Lisa','La Lisa',H),
('Calzada de San Miguel','Cuba','Tamarindo','Luyanó','10 de Octubre',H),('Avenida de la Independencia','Boyeros','Rancho Boyeros','Plaza de la Revolución','Plaza de la Revolución',H),
('Calle 3ra','A','B','Alamar','Habana del Este',H),('Avenida de Alamar','Zona 5','Zona 6','Alamar','Habana del Este',H),('Calle Real','Cotorro','Santa Cruz','Cotorro','Cotorro',H),
('Calle 44','25','27','Playa','Playa',H),('Calle 14','5ta','7ma','Miramar','Playa',H),('Calle 18','3ra','5ta','Miramar','Playa',H),('Calle 11','Línea','M','Vedado','Plaza de la Revolución',H),
]
S='Santiago de Cuba'
L+=[('Calle Heredia','San Félix','Reloj','Centro Histórico','Santiago de Cuba',S),('Calle Enramadas','Calvario','San Pedro','Centro Histórico','Santiago de Cuba',S),('Calle Aguilera','Carnicería','San Basilio','Centro Histórico','Santiago de Cuba',S),
('Calle Trocha','Clarín','San Basilio','Centro Histórico','Santiago de Cuba',S),('Avenida Garzón','Calle 8','Calle 10','Santiago de Cuba','Santiago de Cuba',S),('Calle Santo Tomás','Heredia','Aguilera','Centro Histórico','Santiago de Cuba',S),
('Calle Clarín','Hartmann','Padre Pico','Centro Histórico','Santiago de Cuba',S),('Calle San Basilio','Carnicería','Mayía Rodríguez','Centro Histórico','Santiago de Cuba',S),('Calle 6','Avenida Manduley','Calle 8','Vista Alegre','Santiago de Cuba',S),
('Avenida Manduley','Calle 7','Calle 9','Vista Alegre','Santiago de Cuba',S),('Calle Corona','Aguilera','Heredia','Centro Histórico','Santiago de Cuba',S),('Calle Carnicería','Heredia','Aguilera','Centro Histórico','Santiago de Cuba',S),
('Calle Reloj','Heredia','Aguilera','Centro Histórico','Santiago de Cuba',S),('Calle Hartmann','Clarín','San Jerónimo','Centro Histórico','Santiago de Cuba',S),('Calle San Jerónimo','Hartmann','Clarín','Centro Histórico','Santiago de Cuba',S),
('Calle Padre Pico','San Basilio','Heredia','Centro Histórico','Santiago de Cuba',S),('Avenida de los Libertadores','Calle 4','Calle 6','Santiago de Cuba','Santiago de Cuba',S)]
C='Camagüey'
L+=[('Calle República','Independencia','Cisneros','Centro','Camagüey',C),('Calle Independencia','República','Martí','Centro','Camagüey',C),('Calle Martí','Independencia','Cisneros','Centro','Camagüey',C),
('Calle Avellaneda','Cisneros','Martí','Centro','Camagüey',C),('Calle Cisneros','República','Martí','Centro','Camagüey',C),('Calle Maceo','República','Avellaneda','Centro','Camagüey',C),
('Calle Ignacio Agramonte','Maceo','Cisneros','Centro','Camagüey',C),('Avenida de los Mártires','Calle 5','Calle 7','Camagüey','Camagüey',C),('Calle Lugareño','Independencia','Martí','Centro','Camagüey',C),
('Calle Hurtado','Martí','Padre Valencia','Centro','Camagüey',C),('Calle San Ramón','Martí','República','Centro','Camagüey',C),('Calle Raúl Lamar','Maceo','Cisneros','Centro','Camagüey',C),
('Calle Honda','Lugareño','Martí','Centro','Camagüey',C),('Calle Popular','Maceo','Cisneros','Centro','Camagüey',C),('Calle Francisco Varona','Hurtado','Martí','Centro','Camagüey',C),
('Calle Oriente','Martí','Cisneros','Centro','Camagüey',C),('Calle Rosario','Martí','Cisneros','Centro','Camagüey',C)]
G='Holguín'
L+=[('Calle Maceo','Libertad','Frexes','Centro','Holguín',G),('Calle Libertad','Maceo','Martí','Centro','Holguín',G),('Calle Frexes','Maceo','Martí','Centro','Holguín',G),
('Calle Martí','Libertad','Frexes','Centro','Holguín',G),('Calle Mártires','Maceo','Martí','Centro','Holguín',G),('Calle Aguilera','Maceo','Martí','Centro','Holguín',G),
('Calle Narciso López','Maceo','Martí','Centro','Holguín',G),('Calle Cables','Maceo','Martí','Centro','Holguín',G),('Calle Arias','Maceo','Martí','Centro','Holguín',G),
('Avenida XX Aniversario','Calle 4','Calle 6','Holguín','Holguín',G),('Calle Luz Caballero','Maceo','Martí','Centro','Holguín',G),('Calle Cuba','Maceo','Martí','Centro','Holguín',G),
('Calle Manduley','Libertad','Frexes','Centro','Holguín',G),('Calle Agramonte','Maceo','Martí','Centro','Holguín',G),('Calle Garayalde','Maceo','Martí','Centro','Holguín',G),
('Calle Miró','Maceo','Martí','Centro','Holguín',G)]
L=L[:49]+L[50:] if len(L)==101 else L
assert len(L)==100,len(L)
def num(): return random.choice([random.randint(10,99),random.randint(100,999),random.randint(1000,1999)])
rows=[]
for calle,e1,e2,barrio,mun,prov in L:
    n=num()
    rows.append(dict(calle=calle,num=n,e1=e1,e2=e2,barrio=barrio,mun=mun,prov=prov,
        completa=f"{calle} #{n} entre {e1} y {e2}, {barrio}, {mun}, {prov}, Cuba",
        simple=f"{calle}, {mun}, {prov}, Cuba"))
def q(params):
    url=NOM+'/search?'+urllib.parse.urlencode({**params,'format':'jsonv2','addressdetails':1,'limit':1,'accept-language':'es'})
    t=time.time(); r=json.load(urllib.request.urlopen(url,timeout=30)); return (r[0] if r else None), time.time()-t
def classify(row,res):
    if not res: return 'sin_resultado'
    a=res.get('address',{}); blob=norm(' '.join(str(v) for v in a.values()))+' '+norm(res.get('display_name',''))
    road=norm(a.get('road') or a.get('pedestrian') or a.get('residential') or res.get('name') or '')
    prov_ok=norm(row['prov']) in blob
    mun_ok=norm(row['mun']) in blob or norm(row['barrio']) in blob
    calle=norm(re.sub(r'^(calle|avenida|calzada)\s+','',row['calle'],flags=re.I))
    road_ok=bool(calle) and (calle in road or road in calle and len(road)>2)
    if road_ok and prov_ok and mun_ok: return 'calle_y_zona_correctas'
    if road_ok and prov_ok: return 'calle_correcta_zona_dudosa'
    if prov_ok and mun_ok: return 'zona_correcta_calle_no'
    if prov_ok: return 'solo_provincia'
    return 'erronea'
out={}
for estrategia in ('completa','simple','estructurada'):
    cnt={}; lat=[]
    for r in rows:
        if estrategia=='estructurada': res,dt=q({'street':f"{r['num']} {r['calle']}",'city':r['mun'],'state':r['prov'],'country':'Cuba'})
        else: res,dt=q({'q':r[estrategia]})
        lat.append(dt); c=classify(r,res); cnt[c]=cnt.get(c,0)+1
    tot=sum(cnt.values()); lat.sort()
    out[estrategia]={'total':tot,'conteo':cnt,'pct_calle_y_zona':round(100*cnt.get('calle_y_zona_correctas',0)/tot),'pct_calle_correcta_o_mejor':round(100*(cnt.get('calle_y_zona_correctas',0)+cnt.get('calle_correcta_zona_dudosa',0))/tot),'pct_zona_o_mejor':round(100*(cnt.get('calle_y_zona_correctas',0)+cnt.get('calle_correcta_zona_dudosa',0)+cnt.get('zona_correcta_calle_no',0))/tot),'lat_mediana_ms':round(1000*lat[len(lat)//2]),'lat_p95_ms':round(1000*lat[int(len(lat)*.95)])}
print(json.dumps(out,ensure_ascii=False,indent=1))
json.dump(rows,open(__import__('pathlib').Path(__file__).with_name('a1_direcciones.json'),'w'),ensure_ascii=False,indent=1)
