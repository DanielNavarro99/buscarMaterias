<?php
$host = 'localhost';
$port = 3306;
$dbname = 'tec_chapala';
$username = 'root';
$password = '';
$message = "";  // Variable para el mensaje de éxito

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Si se envía el formulario para agregar o modificar profesor
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger datos del formulario
    $nombre = $_POST['nombre'];
    $apellido = $_POST['apellido'];
    $carrera = $_POST['carrera'];
    $id_materia = $_POST['materia'];
    // Para modificación, se envía el id_profesor oculto en el formulario
    $id_profesor = isset($_POST['id_profesor']) ? $_POST['id_profesor'] : null;

    // Manejo del archivo de imagen
    $directorio = "C:/xampp/htdocs/horarios/";  // ruta que guarda los pdf e imagenes
    $archivo = $directorio . basename($_FILES["horario"]["name"]);
    $tipoArchivo = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));

    // Validar que el archivo sea una imagen o un PDF
    $permitidos = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
    if (!in_array($tipoArchivo, $permitidos)) {
        die("Error: Solo se permiten imágenes JPG, JPEG, PNG, GIF o PDF.");
    }

    // Si se subió un archivo (puede ser opcional en modificación)
    if (!empty($_FILES["horario"]["name"])) {
        if (move_uploaded_file($_FILES["horario"]["tmp_name"], $archivo)) {
            // Si estamos modificando, actualizar la imagen
            if ($id_profesor) {
                $sql = "UPDATE Horarios 
                        SET imagen_horario = :horario, id_carrera = :carrera, id_materia = :id_materia 
                        WHERE id_profesor = :id_profesor";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'horario' => $archivo,
                    'carrera' => $carrera,
                    'id_materia' => $id_materia,
                    'id_profesor' => $id_profesor
                ]);
            }
        } else {
            die("Error al subir la imagen.");
        }
    }

    if ($id_profesor) {
        // Actualizar datos del profesor en caso de modificación
        $sql = "UPDATE Profesores 
                SET nombre = :nombre, apellido = :apellido 
                WHERE id_profesor = :id_profesor";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'nombre' => $nombre,
            'apellido' => $apellido,
            'id_profesor' => $id_profesor
        ]);
        // Si no se subió una nueva imagen, actualizar carrera y materia igualmente
        if (empty($_FILES["horario"]["name"])) {
            $sql = "UPDATE Horarios 
                    SET id_carrera = :carrera, id_materia = :id_materia 
                    WHERE id_profesor = :id_profesor";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'carrera' => $carrera,
                'id_materia' => $id_materia,
                'id_profesor' => $id_profesor
            ]);
        }
        $message = "Profesor modificado exitosamente.";
    } else {
        // Insertar nuevo profesor
        $sql = "INSERT INTO Profesores (nombre, apellido) VALUES (:nombre, :apellido)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['nombre' => $nombre, 'apellido' => $apellido]);
        $id_profesor = $pdo->lastInsertId();

        $sql = "INSERT INTO Horarios (id_profesor, imagen_horario, id_carrera, id_materia) 
                VALUES (:id_profesor, :horario, :carrera, :id_materia)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id_profesor' => $id_profesor,
            'horario' => $archivo,
            'carrera' => $carrera,
            'id_materia' => $id_materia
        ]);
        $message = "Profesor agregado exitosamente.";
    }
}

// Obtener las carreras disponibles
$carrera_sql = "SELECT id_carrera, nombre_carrera FROM Carreras";
$carrera_stmt = $pdo->query($carrera_sql);
$carreras = $carrera_stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener las materias disponibles
$materia_sql = "SELECT id_materia, nombre_materia FROM Materias";
$materia_stmt = $pdo->query($materia_sql);
$materias = $materia_stmt->fetchAll(PDO::FETCH_ASSOC);

// Si se selecciona un profesor para editar
if (isset($_GET['editar'])) {
    $id = $_GET['editar'];
    $sql = "SELECT p.id_profesor, p.nombre, p.apellido, h.imagen_horario, h.id_carrera, h.id_materia 
            FROM Profesores p 
            JOIN Horarios h ON p.id_profesor = h.id_profesor 
            WHERE p.id_profesor = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    $profesor = $stmt->fetch(PDO::FETCH_ASSOC);
    $nombre_imagen = basename($profesor['imagen_horario']);
}

if (isset($_GET['eliminar'])) {
    $idEliminar = $_GET['eliminar'];
    $stmt = $pdo->prepare("DELETE FROM Horarios WHERE id_profesor = :id");
    $stmt->execute(['id' => $idEliminar]);
    $stmt = $pdo->prepare("DELETE FROM Profesores WHERE id_profesor = :id");
    $stmt->execute(['id' => $idEliminar]);
    header("Location: VistaAdmin.php");
    exit;
}

$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';

$sql = "
    SELECT 
        p.id_profesor,
        p.nombre, 
        p.apellido, 
        h.imagen_horario, 
        c.nombre_carrera
    FROM Horarios h
    JOIN Profesores p ON h.id_profesor = p.id_profesor
    JOIN Carreras c ON h.id_carrera = c.id_carrera
    WHERE p.nombre LIKE :busqueda OR p.apellido LIKE :busqueda
";
$stmt = $pdo->prepare($sql);
$stmt->execute(['busqueda' => "%$busqueda%"]);
$datos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrador</title>
    <link rel="stylesheet" href="css/normalize.css">
    <link rel="stylesheet" href="Principal.css">
</head>
<body>
    <div class="barrarosa"></div>

    <section class="fondomorado contenedor">
        <div>
            <img src="Imagenes/LOGOTSJ2.png" width="150" height="500">
        </div>
        <div>
            <a class="iniciarsesion" href="index.html">CERRAR SESION</a>
        </div>
    </section>
    
    <!-- Buscador -->
    <section class="buscador">
     <form method="GET" action="">
        <input type="text" name="busqueda" placeholder="Buscar Profesor" value="<?php echo htmlspecialchars($busqueda); ?>">
        <input type="submit" value="BUSCAR">
     </form>
    </section>

    <!-- Tabla de Profesores -->
    <section class="margenTabla">
        <table border="1">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Apellido</th>
                    <th>Horario</th>
                    <th>Carrera</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($datos as $dato): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($dato['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($dato['apellido']); ?></td>
                        <td><a href="http://localhost:8012/horarios/<?php echo htmlspecialchars(basename($dato['imagen_horario'])); ?>" target="_blank">Ver Horario</a></td>
                        <td><?php echo htmlspecialchars($dato['nombre_carrera']); ?></td>
                        <td>
                            <a href="?editar=<?php echo $dato['id_profesor']; ?>">Seleccionar para modificar</a>
                            <a href="javascript:void(0);" onclick="confirmDelete('<?php echo $dato['id_profesor']; ?>', '<?php echo addslashes($dato['nombre']); ?>', '<?php echo addslashes($dato['apellido']); ?>');">Eliminar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($datos)): ?>
                    <tr><td colspan="5">No se encontraron datos.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <!-- Formulario para Agregar/Modificar Profesor -->
    <section class="margenVista">
        <h2><?php echo isset($profesor) ? "Modificar Profesor" : "Agregar Profesor"; ?></h2>
        <form method="POST" action="" enctype="multipart/form-data">
            <label for="nombre">Nombre:</label>
            <input type="text" name="nombre" value="<?php echo isset($profesor) ? htmlspecialchars($profesor['nombre']) : ''; ?>" required>
            
            <label for="apellido">Apellido:</label>
            <input type="text" name="apellido" value="<?php echo isset($profesor) ? htmlspecialchars($profesor['apellido']) : ''; ?>" required>
    
            <label for="horario">Horario (imagen o PDF):</label>
            <input type="file" name="horario" accept="image/*, application/pdf">
            <?php if (isset($profesor) && !empty($profesor['imagen_horario'])): ?>
            <p>Archivo actual: <a href="http://localhost:8012/horarios/<?php echo htmlspecialchars(basename($profesor['imagen_horario'])); ?>" target="_blank">Ver archivo</a></p>
            <!-- Campo oculto para conservar la ruta actual si no se sube un nuevo archivo -->
            <input type="hidden" name="old_horario" value="<?php echo htmlspecialchars($profesor['imagen_horario']); ?>">
            <?php endif; ?>

            
            <label for="carrera">Carrera:</label>
            <select name="carrera" required>
                <?php foreach ($carreras as $carrera): ?>
                    <option value="<?php echo $carrera['id_carrera']; ?>" <?php echo (isset($profesor) && $profesor['id_carrera'] == $carrera['id_carrera']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($carrera['nombre_carrera']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
    
            <label for="materia">Materia:</label>
            <select name="materia" required>
                <?php foreach ($materias as $materia): ?>
                    <option value="<?php echo $materia['id_materia']; ?>" <?php echo (isset($profesor) && $profesor['id_materia'] == $materia['id_materia']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($materia['nombre_materia']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
    
            <!-- Campo oculto para id_profesor en caso de modificación -->
            <input type="hidden" name="id_profesor" value="<?php echo isset($profesor) ? $profesor['id_profesor'] : ''; ?>">
            <input type="submit" name="agregar" value="<?php echo isset($profesor) ? 'Modificar' : 'Agregar Profesor'; ?>">
            <?php if (isset($profesor)): ?>
                <a href="VistaAdmin.php" class="btn-cancelar">Cancelar</a>
            <?php endif; ?>
        </form>
    </section>

    <!-- Modal de confirmación para eliminación -->
    <div id="confirmModal" class="modal">
      <div class="modal-content">
        <span class="close">&times;</span>
        <p id="modalMessage">¿Estás seguro que quieres borrar a: ?</p>
        <button id="btnConfirm">Aceptar</button>
        <button id="btnCancel">Cancelar</button>
      </div>
    </div>

    <!-- Modal de éxito para mensajes -->
    <div id="successModal" class="modal">
      <div class="modal-content">
        <span class="close-success">&times;</span>
        <p id="successMessage"><?php echo htmlspecialchars($message); ?></p>
        <button id="btnSuccessOk">OK</button>
      </div>
    </div>

    <script>
    function confirmDelete(id, nombre, apellido) {
      var modal = document.getElementById("confirmModal");
      var modalMessage = document.getElementById("modalMessage");
      modalMessage.textContent = "¿Estás seguro que quieres borrar a: " + nombre + " " + apellido + "?";
      
      modal.style.display = "block";
      
      var btnConfirm = document.getElementById("btnConfirm");
      var btnCancel = document.getElementById("btnCancel");
      var spanClose = document.getElementsByClassName("close")[0];
      
      btnConfirm.onclick = function() {
        window.location.href = "VistaAdmin.php?eliminar=" + id;
      };
      
      btnCancel.onclick = function() {
        modal.style.display = "none";
      };
      
      spanClose.onclick = function() {
        modal.style.display = "none";
      };
      
      window.onclick = function(event) {
        if (event.target == modal) {
          modal.style.display = "none";
        }
      };
    }

    document.addEventListener("DOMContentLoaded", function(){
      var modal = document.getElementById("successModal");
      var spanClose = document.getElementsByClassName("close-success")[0];
      var btnOk = document.getElementById("btnSuccessOk");
      
      // Si existe mensaje, mostrar el modal
      <?php if (isset($message) && $message != ""): ?>
        modal.style.display = "block";
      <?php endif; ?>
      
      spanClose.onclick = function() {
        modal.style.display = "none";
      };
      btnOk.onclick = function() {
        modal.style.display = "none";
      };
      
      window.onclick = function(event) {
        if (event.target == modal) {
          modal.style.display = "none";
        }
      };
    });
    </script>

</body>
</html>
