# Mantener Popups

## Garantías del paquete

La disponibilidad, ventana de fechas, frecuencia y consentimiento se vuelven a comprobar al ejecutar. Desactivar conserva configuración y contenido, y evita consultas y ejecución públicas.

## Composiciones de prueba

El perfil mínimo prueba identidad de secciones. `integration` comprueba condiciones y objetivos con Blog/Content Audit, además de overlays en los temas públicos declarados.

Las dependencias exactas y los casos pertenecen a `tests/package.php`;
`composer profiles` enumera las composiciones. Usa `DBCMS_TEST_PROFILE` para
seleccionarlas. Los lockfiles públicos de `tests/fixture` se regeneran con
`composer fixture:lock` al modificar el cierre Composer.

Para comandos Docker, calidad y smoke del distribuible, utiliza la
[guía común de pruebas](https://github.com/dbravoan/dbcms-module-template/blob/main/docs/TESTING.md).
La publicación de este repositorio sigue la
[guía común de publicación](https://github.com/dbravoan/dbcms-module-template/blob/main/docs/PUBLICATION.md).
La versión del módulo es independiente; su línea actual requiere core `^0.1`.

Las reglas de arquitectura y extensión tienen su única fuente en
[core](https://github.com/dbravoan/dbcms-core/tree/main/docs).

La aceptación en navegador vive en `tests/Browser`: fixture mínimo de este módulo
y recorridos admin y móvil en ambas marcas. Usa una aplicación marcada y una base
`dbcms_browser_*`; consulta el procedimiento común de
[pruebas](https://github.com/dbravoan/dbcms-module-template/blob/main/docs/TESTING.md).
