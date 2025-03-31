<?php
// 1. Conectar a la base de datos
$host = 'localhost';
$port = 3306; 
$dbname = 'tec_chapala';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// 2. Obtener todas las carreras para el menú desplegable
$sql_carreras = "SELECT id_carrera, nombre_carrera FROM Carreras";
$stmt_carreras = $pdo->prepare($sql_carreras);
$stmt_carreras->execute();
$carreras = $stmt_carreras->fetchAll(PDO::FETCH_ASSOC);

// 3. Parámetros de búsqueda, paginación y filtro de carrera
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$id_carrera = isset($_GET['id_carrera']) ? (int)$_GET['id_carrera'] : 0; // ID de la carrera seleccionada
$items_por_pagina = 10; // Número de resultados por página
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$pagina_actual = max(1, $pagina_actual); 
$offset = ($pagina_actual - 1) * $items_por_pagina;

// 4. Consulta SQL 
$sql = "
    SELECT 
        h.id_horario,
        m.nombre_materia,
        m.clave_materia,
        p.nombre AS nombre_profesor,
        p.apellido AS apellido_profesor,
        c.nombre_carrera,
        h.semestre,
        h.imagen_horario
    FROM Horarios h
    JOIN Profesores p ON h.id_profesor = p.id_profesor
    JOIN Materias m ON h.id_materia = m.id_materia
    JOIN Carreras c ON h.id_carrera = c.id_carrera
    WHERE 1=1
";

if (!empty($busqueda)) {
    $busqueda = '%' . $busqueda . '%';
    $sql .= " AND (p.nombre LIKE :busqueda OR p.apellido LIKE :busqueda OR m.clave_materia LIKE :busqueda)";
}

if ($id_carrera > 0) {
    $sql .= " AND h.id_carrera = :id_carrera";
}

// 5. Contar el total de registros para la paginación
$sql_count = "SELECT COUNT(*) FROM ($sql) AS total";
$stmt_count = $pdo->prepare($sql_count);
if (!empty($busqueda)) {
    $stmt_count->bindParam(':busqueda', $busqueda, PDO::PARAM_STR);
}
if ($id_carrera > 0) {
    $stmt_count->bindParam(':id_carrera', $id_carrera, PDO::PARAM_INT);
}
$stmt_count->execute();
$total_items = $stmt_count->fetchColumn();
$total_paginas = ceil($total_items / $items_por_pagina);

// 6. Obtener los datos de la página actual
$sql .= " LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
if (!empty($busqueda)) {
    $stmt->bindParam(':busqueda', $busqueda, PDO::PARAM_STR);
}
if ($id_carrera > 0) {
    $stmt->bindParam(':id_carrera', $id_carrera, PDO::PARAM_INT);
}
$stmt->bindParam(':limit', $items_por_pagina, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$materias_paginadas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Materias y Maestros</title>
    <link rel="stylesheet" href="css/normalize.css">
    <link rel="stylesheet" href="Principal.css">
</head>
<body>
    <div class="barrarosa"></div>
    
    <section>
        <section class="fondomorado contenedor">
            <div>   
                <img src="Imagenes/LOGOTSJ2.png" width="250" height="700">
            </div>
            <div>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#ec5a68" stroke-linecap="round" stroke-linejoin="round" width="48" height="48" stroke-width="2">
                    <path d="M5 12l-2 0l9 -9l9 9l-2 0"></path>
                    <path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-7"></path>
                    <path d="M9 21v-6a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v6"></path>
                </svg>
            </div>
            <div>   
                <img src="Imagenes/logos22.png" width="580" height="auto">     
            </div>
            <div>
                <a class="iniciarsesion" href="#">INICIAR SESION</a>
            </div>
        </section>          
    </section>
    
    <header>
        <h1>BUSCAR MATERIA</h1>
    </header>

    <section>
        <h1>FILTRAR POR:</h1>
    </section>
        
    <section>
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" width="48" height="48" stroke-width="2">
            <path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0"></path>
            <path d="M21 21l-6 -6"></path>
        </svg>
        <div>
            <form method="GET" action="">
                <input type="text" name="busqueda" placeholder="BUSCAR" value="">
                <input type="submit" value="Buscar">
            </form>
        </div>
    </section>

    <section class="boton">
        <div>
            <form method="GET" action="">
                <select name="id_carrera" onchange="this.form.submit()">
                    <option value="0">CARRERA</option>
                    <?php foreach ($carreras as $carrera): ?>
                        <option value="<?php echo $carrera['id_carrera']; ?>" 
                            <?php if ($id_carrera == $carrera['id_carrera']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($carrera['nombre_carrera']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </section>

    <!-- Tabla dinámica -->
    <section>
        <table border="1">
            <thead>
                <tr>
                    <th>Clave Materia</th>
                    <th>Materia</th>
                    <th>Maestro</th>
                    <th>Horario</th>
                    <th>Carrera</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($materias_paginadas as $materia): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($materia['clave_materia']); ?></td>
                        <td><?php echo htmlspecialchars($materia['nombre_materia']); ?></td>
                        <td><?php echo htmlspecialchars($materia['nombre_profesor'] . ' ' . $materia['apellido_profesor']); ?></td>
                        <td>
                            <a href="<?php echo htmlspecialchars($materia['imagen_horario']); ?>" target="_blank">
                                Ver Horario
                            </a>
                        </td>
                        <td><?php echo htmlspecialchars($materia['nombre_carrera']); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($materias_paginadas)): ?>
                    <tr><td colspan="5">No se encontraron resultados.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <!-- Paginación -->
    <section>
        <p>Items por página: <?php echo $items_por_pagina; ?></p>
        <p><?php echo ($offset + 1); ?>-<?php echo min($offset + $items_por_pagina, $total_items); ?> de <?php echo $total_items; ?></p>
        <?php if ($pagina_actual > 1): ?>
            <a href="?pagina=<?php echo $pagina_actual - 1; ?>&id_carrera=<?php echo $id_carrera; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="48" height="48">
                    <path d="M17 6h-6a1 1 0 0 0 -.78 .375l-4 5a1 1 0 0 0 0 1.25l4 5a1 1 0 0 0 .78 .375h6l.112 -.006a1 1 0 0 0 .669 -1.619l-3.501 -4.375l3.5 -4.375a1 1 0 0 0 -.78 -1.625z"></path>
                </svg>
            </a>
        <?php endif; ?>
        <?php if ($pagina_actual < $total_paginas): ?>
            <a href="?pagina=<?php echo $pagina_actual + 1; ?>&id_carrera=<?php echo $id_carrera; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="48" height="48">
                    <path d="M7 6l-.112 .006a1 1 0 0 0 -.669 1.619l3.501 4.375l-3.5 4.375a1 1 0 0 0 .78 1.625h6a1 1 0 0 0 .78 -.375l4 -5a1 1 0 0 0 0 -1.25l-4 -5a1 1 0 0 0 -.78 -.375h-6z"></path>
                </svg>
            </a>
        <?php endif; ?>
    </section>

    <section class="colornegro">
        <div>
            <img src="Imagenes/logoblacno.svg" width="150" height="500">
        </div>
        <div>   
            <a href="https://www.facebook.com/TecSJ" target="_blank">
                <img src="Imagenes/face.svg" width="50rem" height="50rem">
            </a>
        </div>
        <a href="https://www.youtube.com/@TecSJ" target="_blank">
            <img src="Imagenes/youtube.svg" width="50rem" height="50rem">
        </a>
        <div>
            <a class="iniciarsesion" href="https://tecmm.edu.mx/oferta-educativa"> Oferta educativa</a>
        </div>
        <div>
            <a class="iniciarsesion" href="https://consultapublicamx.plataformadetransparencia.org.mx/vut-web/faces/view/consultaPublica.xhtml?idEntidad=MTQ=&idSujetoObligado=MTM3OTE=#inicio"> Plataforma Nacional de Transparencia</a>
        </div>
    </section>

    <div>
        <img src="Imagenes/logos2.png" width="1000rem" height="1000rem">
    </div>
</body>
</html>