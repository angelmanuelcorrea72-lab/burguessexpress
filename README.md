# Sistema de pedidos para local de comida rápida (PHP + MySQL) — v3

## Instalación nueva en XAMPP (Windows)
1. Copia la carpeta `comida` dentro de `C:\xampp\htdocs\`.
2. Inicia **Apache** y **MySQL** en XAMPP.
3. En `http://localhost/phpmyadmin` → **Importar** → `sql/database.sql` → Continuar.
4. Si tu MySQL tiene contraseña, edita `config.php`.
5. Tienda: `http://localhost/comida/` — Panel del local: `http://localhost/comida/admin/`
6. Usuario inicial: `admin@local.com` / `admin123` (cámbiala en Ajustes).

## Actualizar una instalación que ya tienes
Haz copia de seguridad de la base (phpMyAdmin → Exportar) y copia los archivos nuevos encima (conserva tu `config.php`).
Luego importa en tu base, **una sola vez y en orden**, solo los que te falten:
- Vienes de la **v1**: `sql/actualizacion_v2.sql` y después `sql/actualizacion_v3.sql`.
- Vienes de la **v2**: solo `sql/actualizacion_v3.sql`.

## Novedades de la v3
| Novedad | Dónde |
|---|---|
| Reporte «Detalle de pedidos»: n.º de pedido, cliente (nombre, teléfono, correo), dirección y mapa, productos con opciones, importes, cupón, pago, repartidor, horas de asignación y entrega, tiempos. Con filtros (fechas, estado, tipo, repartidor, pago, búsqueda) y **exportación a Excel (CSV)** | Panel → Reportes → Detalle de pedidos |
| Reporte de repartidores: asignados, entregados, en curso, cancelados, tiempo promedio, envíos cobrados, efectivo a rendir | Panel → Reportes → Repartidores |
| Ficha completa de cada pedido, con cambio de estado, repartidor y tiempo | Pedidos → «Ver ficha» |
| **Asignación de repartidor corregida** (el selector se bloqueaba al tocarlo) + aviso al repartidor por WhatsApp | Panel → Pedidos |
| **Categorías**: pantalla propia (crear, renombrar, ordenar, activar/ocultar, eliminar moviendo sus productos) | Panel → Categorías |
| **Panel del repartidor** para su celular (ver más abajo) | `…/repartidor/` (entra por el login normal) |
| Accesos de repartidores (alta, cambio de clave, quitar acceso, activar/desactivar) | Panel → Delivery |
| WhatsApp con código de país (0981… → 595981…) en todos los enlaces | Ajustes → «Código de país» |

## Panel del repartidor
1. En **Delivery** crea el repartidor con correo y contraseña (o dale acceso a uno existente).
2. Él entra desde su celular en la misma página de login: lo lleva directo a **Mis pedidos**.
3. Ve solo los pedidos que le asignes: dirección, **mapa «Cómo llegar»**, productos, nota del cliente y **cuánto cobrar** (o si ya está pagado).
4. Botones de **WhatsApp al cliente**, **Llamar** y **Avisar al local**.
5. Confirma «Salgo a entregar» y «Confirmar entrega»: el cliente ve el cambio en su seguimiento.
6. Cuando le asignan un pedido suena un aviso y vibra (toca «Activar sonido y avisos» una vez y deja la pantalla abierta).
7. **Mi historial**: pedidos entregados por período, tiempo promedio, envíos y **efectivo a rendir**.

## Notas importantes
- Los avisos funcionan mientras la pantalla del repartidor (o del cliente) siga abierta en el navegador; con HTTPS en producción.
- El repartidor no ve reportes ni datos del negocio: solo sus pedidos.
- Si quitas o eliminas un repartidor, sus pedidos entregados quedan en el historial; los que estaban en curso quedan sin asignar.
- Correo, impresión de tickets, cupones, zonas, opciones de producto y pago en línea (Bancard): igual que la v2 (ver notas de esa versión en `config.php`).
  Bancard sigue sin probarse contra el servidor real: valídalo en su sandbox antes de cobrar.

## Seguridad aplicada
Contraseñas con hash, sesiones protegidas, token CSRF, consultas preparadas (PDO), precios y cupones recalculados en el servidor,
cada repartidor solo puede ver y modificar SUS pedidos (verificado en el servidor), transiciones de estado controladas,
tokens de recuperación con hash y de un solo uso, subida de imágenes validada y `uploads/` sin ejecución de PHP.
