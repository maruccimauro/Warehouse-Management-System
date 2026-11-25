# Transacciones

Cada pantalla del sistema es una transacción con un código. Los códigos siguen los de SAP cuando la transacción tiene un equivalente y usan el rango `ZW` para las propias, porque `Z` es el espacio de nombres de cliente en SAP.

| Código | Título | Carpeta del menú | Permiso | Página |
| - | - | - | - | - |
| `SU3` | Parámetros de usuario | Sistema ▸ Datos de usuario | Ninguno: cualquier usuario con sesión | [Parámetros de usuario](/primeros-pasos/parametros-de-usuario) |

## Diferencias con SAP {#diferencias-con-sap}

### SU3 {#su3-sap}

Reúne lo que en SAP está repartido entre SU3 y la configuración de SAP GUI: el idioma, el parámetro `LGN` y el cambio de contraseña. Por ahora el único parámetro es `LGN`, que en SAP corresponde al número de almacén de WM.
