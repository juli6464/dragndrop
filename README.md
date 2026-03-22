# local_dragndrop - Categorías con arrastrar y soltar

Plugin local de Moodle que muestra las categorías del banco de preguntas organizadas por **cursos** con funcionalidad de **drag and drop** para reordenar y mover categorías.

## Características

- Vista jerárquica: **Curso** → **Categorías** → **Subcategorías**
- Arrastrar y soltar para mover categorías entre niveles o reordenarlas
- Estilo similar a jQuery UI Smoothness (fondo claro, bordes redondeados)
- Enlaces a editar categorías y al banco de preguntas
- Acceso directo desde Administración del sitio → Plugins → Plugins locales → Drag and Drop Categories

## Instalación

1. Coloca la carpeta `dragndrop` en `moodle/local/`
2. Visita **Notificaciones** para completar la instalación
3. (Opcional) Compila los módulos AMD ejecutando `grunt amd` en la raíz de Moodle

## Uso

- **Todas las categorías**: `/local/dragndrop/index.php`
- **Un curso**: `/local/dragndrop/index.php?courseid=2`
- También: Administración del sitio → Plugins → Plugins locales → Drag and Drop Categories

Requiere la capacidad `moodle/question:managecategory` en el contexto del curso.

## Archivos principales

- `index.php` - Página principal
- `ajax.php` - Endpoint AJAX para guardar movimientos
- `lib.php` - Funciones de datos y render
- `amd/src/dragndrop.js` - Lógica drag and drop ([SortableJS](https://sortablejs.github.io/Sortable/) para listas anidadas)
- `styles.css` - Estilos tipo Smoothness

## Agregar botón de Arrastrar y soltar categorias
- Agrega  Debajo de echo $renderer->render($qbankaction);

// Botón hacia /local/dragndrop/index.php
$dragndropurl = new moodle_url('/local/dragndrop/index.php');

//  link drag n drop:
echo html_writer::link(
    $dragndropurl,
    'Arrastrar y soltar categorías',
    ['class' => 'btn btn-secondary', 'style' => 'margin-top: 5px; margin-bottom: 10px;']
); `
