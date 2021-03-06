<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class RutasController extends \BaseController {

    public function __construct() {
        $this->beforeFilter('serviceAuth', array('only' =>
            array('postCreate', 'postUpdate', 'getDestroy')));
    }

    public function getRutasbyid() {
        $user_id = Input::get('user_id');
        $area_id = Input::get('area_id');
        $rutas = DB::select('select route_id, route_name from gs_user_routes where user_id = ' . $user_id . ' and group_id = ' . $area_id . ' order by route_name asc');
        return Response::json(array('rutas' => $rutas));
    }

    public function getAllinforutasbyid() {
        $user_id = Input::get('user_id');
        $rutas = Ruta::where('user_id', '=', $user_id)->get();
        return Response::json(array('rutas' => $rutas));
    }

    public function getRutas() {
        $user_id = Input::get('user_id');
        $sql = "select route_id, route_name from gs_user_routes where user_id = " . $user_id;
        $rutas = DB::select($sql);
        return Response::json(array('rutas' => $rutas));
    }

    //Metodo que permite asignar rutas a un despachador
    public function postSaveasignacionrutas() {
        $user_id = Input::get("user_id");
        $area_id = Input::get("area_id");
        $route_id = Input::get("route_id");
        $despachador_id = Input::get("despachador_id");
        date_default_timezone_set('America/Bogota');
        $time = time();
        $fecharegistro = date("Y-m-d H:i:s", $time);

        //Insertar el registro de la asignacion del despachador a la ruta(s)
        //verificamos que el registro no se encuentre en la BD
        $sql = "select count(*) conteo from gs_despachador_ruta where desp_id = " . $despachador_id . " and area_id = " . $area_id
                . " and route_id = " . $route_id;
        $results = DB::select($sql);
        if ($results[0]->conteo > 0) {
            return Response::json(array('success' => false, 'mensaje' => "No se puede guardar. Ya existe la ruta seleccionada a este despachador. Intente de nuevo."));
        }

        $sql = "insert into gs_despachador_ruta(desp_id, user_id, area_id, route_id, fecha_asignacion) values("
                . "" . $despachador_id
                . "," . $user_id
                . "," . $area_id
                . "," . $route_id
                . ",'" . $fecharegistro
                . "')";
        try {
            DB::beginTransaction();
            DB::insert($sql);
            DB::commit();
            return Response::json(array('success' => true, 'mensaje' => "El registro se ha guardado correctamente"));
        } catch (Exception $e) {
            DB::rollback();
            return Response::json(array('error' => "No se puede guardar el registro. " . $e, 'error' => true));
        }
    }

    //MEtodo que carga las rutas que el despachador tiene asignadas por area
    public function getRutasdespachador() {
        $data = Input::all();
        $sql = "select * from gs_info_despachador where user_id = " . $data["user_id"];
        $result = DB::select($sql);
        if (count($result) > 0) {
            $sql = "select dr.route_id, ur.route_name from gs_despachador_ruta dr "
                    . " left join gs_user_routes ur ON dr.route_id = ur.route_id"
                    . " where dr.desp_id = " . $result[0]->id
                    . " and dr.area_id = " . $data["area_id"];
        } else {
            return;
        }

        try {
            DB::beginTransaction();
            $rutas = DB::select($sql);
            DB::commit();
            return Response::json(array('rutas' => $rutas));
        } catch (Exception $e) {
            return Response::json(array('mensaje' => "No se pudo cargar los registro de la BD: " . $e, 'error' => true));
        }
    }

    public function getRutasxdespachadorid() {
        $data = Input::all();
        $sql = "select * from gs_info_despachador where user_id = " . $data["user_id"];
        $result = DB::select($sql);
        if (count($result) > 0) {
            $sql = "select dr.route_id, ur.route_name from gs_despachador_ruta dr "
                    . " left join gs_user_routes ur ON dr.route_id = ur.route_id"
                    . " where dr.desp_id = " . $result[0]->id;
        } else {
            return;
        }

        try {
            DB::beginTransaction();
            $rutas = DB::select($sql);
            DB::commit();
            return Response::json(array('rutas' => $rutas));
        } catch (Exception $e) {
            return Response::json(array('mensaje' => "No se pudo cargar los registro de la BD: " . $e, 'error' => true));
        }
    }

    public function getGruposvehiculosbyrutasid() {
        $data = Input::all();
//Selecciono el id de la empresa para que el despachador trabajo y poder localizar los gruposrutas registrados por el usuario empresa
        $sql = "select empresa_id from gs_info_despachador where user_id = " . $data["user_id"];
        $result = DB::select($sql);
        $query = "select gr.group_id, guog.group_name from gs_gruposrutas gr "
                . "join gs_user_object_groups guog ON guog.group_id = gr.group_id "
                . "where gr.user_id = " . $result[0]->empresa_id
                . " and gr.route_id = " . $data["route_id"];
        $grupos = DB::select($query);

//        if(count($grupos)>0){
//          for($i=0; $i < count($grupos); $i++){
//             $qry = "select gso.object_id, gob.name, gso.imei "
//                     . "from gs_user_objects gso "
//                     . "join gs_objects gob on gob.imei = gso.imei "
//                     . "where gso.user_id = " .$result[0]->empresa_id
//                     . " and gso.group_id = " .$grupos[$i]->group_id
//                     . ";" ;
//            $vehiculos = DB::select($qry);
//            array_push($array_result,$vehiculos);
//            echo($qry);
//            break;
//          }   
//        } 
        return Response::json(array('grupos' => $grupos));
    }

    //Metodo que lista los vehiculos que se encuentran libre de despacho temporal y de recorrido
    public function getVehiculosbygroupid() {
        $data = Input::all();
        //Selecciono el id de la empresa para que el despachador trabajo y poder localizar los vehiculos asociados al grupo
        $sql = "select empresa_id from gs_info_despachador where user_id = " . $data["user_id"];
        $result = DB::select($sql);
        $qry = "select gso.object_id, gob.name, gso.imei "
                . "from gs_user_objects gso "
                . "join gs_objects gob on gob.imei = gso.imei "
                . "where gso.user_id = " . $result[0]->empresa_id
                . " and gso.group_id = " . $data["group_id"]
                . ";";

        $vehiculos = DB::select($qry);
        $sql = "select dt.object_id from gs_despacho_temporal dt where dt.user_id = " . $data["user_id"]
                . " and dt.estado = 2 or dt.estado = 3 or dt.estado = 4;"
        ;
        $vehiculos_temporal = DB::select($sql);
        $vehiculos_aux = $vehiculos;
        //Si hay vehiculos en despacho temporal descartarlos de la lista general de vehiculos        
        if (count($vehiculos_temporal) > 0) {
            for ($i = 0; $i < count($vehiculos); $i++) {
                $j = 0;
                $bandera = false;
                while ($j < count($vehiculos_temporal)) {
                    if ($vehiculos[$i]->object_id == $vehiculos_temporal[$j]->object_id) {
                        if ($bandera == false) {
                            unset($vehiculos_aux[array_search($vehiculos[$i], $vehiculos)]);
                            $bandera = true;
                        }
                    }
                    $j++;
                }
            }
        }
        if (count($vehiculos_aux) > 0) {
            return Response::json(array('vehiculos' => $vehiculos_aux));
        } else {
            return Response::json(array('empty' => true, 'mensaje' => 'No hay vehiculos asociados al grupo. Contacte al administrador.'));
        }
    }

    public function getVueltasbyruta() {
        $user_id = Input::get('user_id');
        $area_id = Input::get('area_id');
        $ruta_id = Input::get('ruta_id');
        $fechac = Input::get('fecha');

        $sql = "SELECT DISTINCT numero_recorrido FROM despachos WHERE ruta_id=" . $ruta_id
                . " AND hora_salida >= (SELECT DATE_FORMAT('" . $fechac . "' , '%Y-%m-%d 00:00:00'))"
                . " AND estado_id = 4 ORDER BY 1 ASC;";
        $vueltas = DB::select($sql);
        return Response::json(array('vueltas' => $vueltas));
    }

    public function getVehiculosbyruta() {
        $user_id = Input::get('user_id');
        $area_id = Input::get('area_id');
        $ruta_id = Input::get('ruta_id');
        $fechac = Input::get('fecha');

        $sql = "SELECT DISTINCT d. imei, go.name "
                . " FROM despachos d INNER JOIN gs_objects go ON go.imei = d.imei "
                . " INNER JOIN gs_user_routes gur ON gur.route_id=d.ruta_id WHERE ruta_id=" . $ruta_id
                . " AND d.hora_salida >= (SELECT DATE_FORMAT('" . $fechac . "', '%Y-%m-%d 00:00:00'))"
                . " AND d.estado_id = 4 ORDER BY 1 ASC;";
        $vehiculos = DB::select($sql);
        return Response::json(array('vehiculos' => $vehiculos));
    }

    public function getRutasbygroupid() {
        $data = Input::all();
        $sql = "SELECT gub.object_id, gub.group_id, r.route_id, r.route_name
            FROM gs_user_objects gub 
            JOIN gs_gruposrutas gr ON gr.group_id = gub.group_id
            JOIN gs_user_routes r ON r.route_id = gr.route_id
            WHERE gub.user_id = " . $data["user_id"]
                . " and gub.object_id = " . $data["vehiculo_id"]
                . ";";
        $rutas = DB::select($sql);
        return Response::json(array('rutas' => $rutas));
    }

    public function getRutasbydespachadorid() {
        $data = Input::all();
        $sql = "select dr.route_id, r.route_name, dr.fecha_asignacion"
                . " from gs_despachador_ruta dr join gs_user_routes r ON r.route_id = dr.route_id"
                . " where dr.user_id = " . $data["user_id"] . " and dr.area_id = " . $data["area_id"] . " and dr.desp_id = " . $data["despachador_id"];
        $result = DB::select($sql);
        return Response::json(array('rutasasignadas' => $result, 'success' => true));
    }

    public function postDeleteasignacionrutas() {
        $data = Input::all();
        $sql = "delete from gs_despachador_ruta where desp_id = " . $data["despachador_id"] . " and area_id = " . $data["area_id"]
                . " and route_id = " . $data["route_id"];

        try {
            DB::beginTransaction();
            DB::delete($sql);
            DB::commit();
            return Response::json(array('success' => true, 'mensaje' => "El registro fue borrado satisfactoriamente."));
        } catch (Exception $e) {
            return Response::json(array('mensaje' => "No se pudo borrar el registro de la BD: " . $e, 'error' => true));
        }
    }

}
