<?php
namespace App\Controllers;

class EntrenadorController {
    private $db;
    public function __construct($db){$this->db=$db;if(session_status()===PHP_SESSION_NONE)session_start();}
    private function json($data,$status=200){http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE);}
    private function autorizado(){if(empty($_SESSION['usuario_id'])||($_SESSION['usuario_rol']??'')!=='entrenador'){$this->json(['status'=>'error','message'=>'Se requiere una sesión de entrenador'],401);return false;}return (int)$_SESSION['usuario_id'];}
    public function resumen(){
        $entrenador=$this->autorizado();if(!$entrenador)return;
        $socios=$this->db->prepare("SELECT u.ID_usuario AS id,p.Nombre AS nombre,p.Correo AS email,COALESCE((SELECT m.Estado FROM Membresias m WHERE m.ID_usuario=u.ID_usuario ORDER BY m.Fecha_vencimiento DESC LIMIT 1),'Sin membresía') AS membresia FROM Socio_Entrenador se INNER JOIN Usuarios u ON u.ID_usuario=se.ID_socio INNER JOIN Persona p ON p.ID_usuario=u.ID_usuario WHERE se.ID_entrenador=:entrenador ORDER BY p.Nombre");$socios->execute(['entrenador'=>$entrenador]);
        $ejercicios=$this->db->query('SELECT ID_ejercicio AS id,Nombre AS nombre,Grupo_muscular AS grupo FROM Ejercicios WHERE Activo=1 ORDER BY Nombre')->fetchAll(\PDO::FETCH_ASSOC);
        $rutinas=$this->db->prepare('SELECT r.ID_rutina AS id,r.Nombre_rutina AS nombre,r.Descripcion AS descripcion,p.Nombre AS socio,DATE_FORMAT(r.Fecha_creacion,"%d/%m/%Y") AS fecha FROM Rutinas r INNER JOIN Usuario_Rutina ur ON ur.ID_rutina=r.ID_rutina INNER JOIN Persona p ON p.ID_usuario=ur.ID_usuario WHERE r.ID_entrenador=:entrenador AND r.Activa=1 ORDER BY r.Fecha_creacion DESC');$rutinas->execute(['entrenador'=>$entrenador]);
        $this->json(['status'=>'success','socios'=>$socios->fetchAll(\PDO::FETCH_ASSOC),'ejercicios'=>$ejercicios,'rutinas'=>$rutinas->fetchAll(\PDO::FETCH_ASSOC)]);
    }
    public function crearRutina(){
        $entrenador=$this->autorizado();if(!$entrenador)return;$in=json_decode(file_get_contents('php://input'),true)?:[];$socio=(int)($in['socio_id']??0);$nombre=trim((string)($in['nombre']??''));$descripcion=trim((string)($in['descripcion']??''));$ejercicios=$in['ejercicios']??[];
        if(!$socio||$nombre===''||!is_array($ejercicios)||!count($ejercicios))return $this->json(['status'=>'error','message'=>'Socio, nombre y al menos un ejercicio son obligatorios'],400);
        $check=$this->db->prepare('SELECT ID_socio FROM Socio_Entrenador WHERE ID_socio=:socio AND ID_entrenador=:entrenador');$check->execute(['socio'=>$socio,'entrenador'=>$entrenador]);if(!$check->fetchColumn())return $this->json(['status'=>'error','message'=>'El socio no está asignado a este entrenador'],403);
        try{$this->db->beginTransaction();$stmt=$this->db->prepare('INSERT INTO Rutinas (Nombre_rutina,Descripcion,ID_entrenador) VALUES (:nombre,:descripcion,:entrenador)');$stmt->execute(['nombre'=>$nombre,'descripcion'=>$descripcion,'entrenador'=>$entrenador]);$rutina=(int)$this->db->lastInsertId();$asignar=$this->db->prepare('INSERT INTO Usuario_Rutina (ID_usuario,ID_rutina) VALUES (:socio,:rutina)');$asignar->execute(['socio'=>$socio,'rutina'=>$rutina]);$item=$this->db->prepare('INSERT INTO Rutina_Ejercicio (ID_rutina,ID_ejercicio,Series,Repeticiones,Orden,Tiempo_descanso,Tiempo_ejecucion,Notas) VALUES (:rutina,:ejercicio,:series,:reps,:orden,:descanso,:tiempo,:notas)');foreach($ejercicios as $orden=>$ejercicio){$item->execute(['rutina'=>$rutina,'ejercicio'=>(int)($ejercicio['id']??0),'series'=>max(1,(int)($ejercicio['series']??3)),'reps'=>trim((string)($ejercicio['repeticiones']??'10')),'orden'=>$orden+1,'descanso'=>max(0,(int)($ejercicio['descanso']??90)),'tiempo'=>!empty($ejercicio['tiempo'])?(int)$ejercicio['tiempo']:null,'notas'=>trim((string)($ejercicio['notas']??''))]);}$this->db->commit();$this->json(['status'=>'success','message'=>'Rutina creada y asignada']);}catch(\Throwable $error){if($this->db->inTransaction())$this->db->rollBack();$this->json(['status'=>'error','message'=>'No se pudo guardar la rutina'],500);}
    }
}
