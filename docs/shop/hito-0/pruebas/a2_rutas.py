# A2 (COMR-97): 10 paradas en La Habana y en Santiago de Cuba con OSRM (:15000) y VROOM (:13000).
# Coordenadas aproximadas de lugares públicos conocidos; no hay datos personales.
import json, time, urllib.request
OSRM='http://localhost:15000'; VROOM='http://localhost:13000'
def get(u): return json.load(urllib.request.urlopen(u, timeout=60))
def post(u,b):
    r=urllib.request.Request(u,json.dumps(b).encode(),{'Content-Type':'application/json'}); return json.load(urllib.request.urlopen(r,timeout=120))
HABANA=[('Capitolio (almacén)',23.1352,-82.3598),('Hotel Nacional',23.1409,-82.3979),('Plaza de la Revolución',23.1226,-82.3868),('Castillo del Príncipe',23.1291,-82.3825),('Parque Central',23.1360,-82.3580),('Fábrica de Arte',23.1420,-82.4001),('Acuario Nacional',23.1118,-82.4337),('Marianao',23.0825,-82.4300),('Estadio Latinoamericano',23.1120,-82.3490),('Alamar',23.1670,-82.2750)]
SANTIAGO=[('Parque Céspedes (almacén)',20.0213,-75.8283),('Castillo del Morro',19.9669,-75.8745),('Cuartel Moncada',20.0243,-75.8159),('Plaza de Marte',20.0233,-75.8209),('Cementerio Santa Ifigenia',20.0368,-75.8306),('Estadio Guillermón Moncada',20.0165,-75.8000),('Terminal de ómnibus',20.0345,-75.7960),('Universidad de Oriente',20.0190,-75.8280),('Hospital Provincial',20.0390,-75.8200),('Vista Alegre',20.0090,-75.8120)]
res={}
for city,pts in (('La Habana',HABANA),('Santiago de Cuba',SANTIAGO)):
    snaps=[]
    for n,la,lo in pts:
        r=get(f"{OSRM}/nearest/v1/driving/{lo},{la}?number=1")
        snaps.append(r['waypoints'][0]['distance'])
    jobs=[{'id':i,'location':[lo,la]} for i,(n,la,lo) in enumerate(pts) if i>0]
    veh=[{'id':1,'start':[pts[0][2],pts[0][1]],'end':[pts[0][2],pts[0][1]]}]
    t=time.time(); out=post(VROOM+'/',{'vehicles':veh,'jobs':jobs}); dt=time.time()-t
    steps=[s for s in out['routes'][0]['steps'] if s['type']=='job']
    order=[pts[s['job']][0] for s in steps]
    # tiempo de OSRM solo: matriz
    coords=';'.join(f"{lo},{la}" for n,la,lo in pts)
    t=time.time(); tbl=get(f"{OSRM}/table/v1/driving/{coords}?annotations=duration,distance"); dtt=time.time()-t
    # ruta completa siguiendo el orden de VROOM
    seq=[pts[0]]+[pts[s['job']] for s in steps]+[pts[0]]
    c=';'.join(f"{lo},{la}" for n,la,lo in seq)
    t=time.time(); rt=get(f"{OSRM}/route/v1/driving/{c}?overview=false&steps=false"); dtr=time.time()-t
    res[city]={'snap_m_max':round(max(snaps)),'snap_m_media':round(sum(snaps)/len(snaps)),'vroom_s':round(dt,3),'osrm_table_s':round(dtt,3),'osrm_route_s':round(dtr,3),
      'orden':order,'distancia_km':round(rt['routes'][0]['distance']/1000,1),'duracion_min':round(rt['routes'][0]['duration']/60,1),
      'vroom_resumen':{'coste_s':out['summary']['cost'],'sin_asignar':len(out['unassigned'])}}
print(json.dumps(res,ensure_ascii=False,indent=1))
