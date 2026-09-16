# Manual de usuario — AS-NegocioOS

Sistema de gestión comercial (mini-ERP): clientes, productos, ventas, inventario, reportes,
configuración y auditoría.

---

## Tabla de contenidos

1. [Introducción](#1-introducción)
2. [Primeros pasos](#2-primeros-pasos)
3. [La interfaz](#3-la-interfaz)
4. [Panel de control](#4-panel-de-control)
5. [Clientes](#5-clientes)
6. [Productos](#6-productos)
7. [Ventas](#7-ventas)
8. [Inventario](#8-inventario)
9. [Reportes](#9-reportes)
10. [Configuración](#10-configuración)
11. [Auditoría](#11-auditoría)
12. [Usuarios](#12-usuarios)
13. [Roles y permisos](#13-roles-y-permisos)
14. [Preguntas frecuentes](#14-preguntas-frecuentes)

---

## 1. Introducción

**AS-NegocioOS** es un sistema de gestión comercial para pequeñas y medianas empresas que
permite administrar clientes, productos, ventas con facturación e inventario en un solo lugar.

El acceso está controlado por **roles**: cada usuario ve únicamente los módulos y acciones que
su rol le permite. Los roles disponibles son:

- **Administrador**: acceso completo a todos los módulos.
- **Vendedor**: crea ventas, administra clientes y consulta productos, ventas y reportes.
- **Encargado**: administra productos, inventario y reportes; consulta y registra ventas, y
  administra clientes.

Consulta la [tabla de roles y permisos](#13-roles-y-permisos) para ver el detalle.

---

## 2. Primeros pasos

### 2.1 Iniciar sesión

1. Abre en tu navegador la dirección del sistema (ej. `https://adlersystems.com/as-negocioos`).
2. Si no tienes sesión iniciada, verás la pantalla de **Inicio de sesión**.
3. Escribe tu **correo electrónico** y tu **contraseña**.
4. Pulsa el botón **Ingresar**.

La aplicación te llevará al [Panel de control](#4-panel-de-control) con tu nombre y rol.

> El sistema es privado: un usuario debe ser creado por un administrador para poder acceder.

### 2.2 ¿Olvidaste tu contraseña?

1. En la pantalla de inicio de sesión pulsa el enlace **¿Olvidaste tu contraseña?**.
2. Escribe el correo de tu cuenta y pulsa el botón para enviar el enlace de restablecimiento.
3. Revisa tu correo y abre el enlace recibido.
4. Escribe tu nueva contraseña y confírmala.

> Si no recibes el correo, pide a un administrador que verifique la configuración de correo
> del sistema.

---

## 3. La interfaz

Una vez dentro, la interfaz tiene dos zonas principales:

### 3.1 Barra lateral (menú)

Muestra los módulos a los que tienes acceso según tu rol. Haz clic en cualquier opción para
abrirlo. Los módulos pueden ser:

`Panel de control` · `Clientes` · `Productos` · `Ventas` · `Inventario` · `Reportes` ·
`Auditoría` · `Usuarios` · `Configuración`

- El menú se **contrae** con el doble flecha (en pantallas grandes) para dejar más espacio.
- En pantallas pequeñas (móvil/teléfono), el menú se oculta y se abre con el botón de **menú**
  (tres líneas) en la parte superior.

### 3.2 Barra superior

Desde aquí puedes:

- **Cambiar el idioma** (globo 🌐): alterna entre **Español** e **English**. El cambio aplica
  a toda la aplicación durante tu sesión.
- **Cambiar el tema** (sol/luna): alterna entre modo **claro** y **oscuro**.
- **Menú de usuario**: muestra tu nombre y correo, e incluye la opción **Cerrar sesión**.

### 3.3 Cerrar sesión

1. Pulsa sobre tu nombre (esquina superior derecha).
2. Selecciona **Cerrar sesión**.

---

## 4. Panel de control

El panel de control es la pantalla inicial después de iniciar sesión. Resume la actividad del
negocio:

### 4.1 Métricas principales

Cuatro tarjetas con los totales globales:

- **Clientes**: total de clientes registrados.
- **Productos**: total de productos del catálogo.
- **Ventas**: total de ventas registradas.
- **Cuentas por cobrar**: total pendiente de las ventas no pagadas (solo las que tienen
  cliente asignado).

### 4.2 Indicadores (KPIs)

- **Ingresos de hoy**: total vendido en el día actual.
- **Ingresos del mes**: total vendido en el mes en curso.
- **Ticket promedio**: monto promedio por venta.
- **Valor del inventario**: costo total del stock actual (costo de producción × stock).

### 4.3 Alertas de inventario

Muestra avisos sobre productos que requieren atención, con acceso directo al listado de
productos:

- **Agotados**: productos sin stock.
- **Stock bajo**: productos en o por debajo de su stock mínimo.
- **Por vencer**: productos con fecha de vencimiento próxima.

### 4.4 Gráficos

Se muestran cuando existen ventas registradas:

- **Ventas por mes** (últimos 6 meses).
- **Ventas por vendedor**.
- **Productos más vendidos**.
- **Evolución de ingresos** (últimos 12 meses).

### 4.5 Cliente top y ventas recientes

- **Cliente top**: el cliente con mayor total comprado.
- **Ventas recientes**: las últimas 8 ventas, con número de factura, cliente, vendedor,
  monto y antigüedad. Pulsa **Ver todas** para ir a [Ventas](#7-ventas).

---

## 5. Clientes

El módulo de **Clientes** te permite llevar un registro completo de tus clientes y su historial
de compras.

### 5.1 Crear un cliente

1. Entra a **Clientes** y pulsa el botón **Nuevo cliente** (o **Crear**).
2. Completa el formulario:
   - **Nombre** *(obligatorio)*.
   - **NIT** *(opcional)*.
   - **Correo electrónico** *(opcional)*.
   - **Teléfono** *(opcional)*.
   - **Dirección** *(opcional)*.
   - **Idioma de preferencia**: Español o English.
   - **Notas** *(opcional)*: cualquier información adicional.
3. Pulsa **Guardar**.

### 5.2 Ver un cliente

En el listado, pulsa sobre el nombre del cliente (o el botón **Ver**) para abrir su ficha con:
- Datos de contacto.
- Resumen de compras (cantidad y total).
- Saldo pendiente de sus ventas no pagadas.
- Historial de sus ventas.

### 5.3 Editar un cliente

1. En el listado, localiza el cliente y pulsa **Editar**.
2. Modifica los datos necesarios.
3. Pulsa **Guardar cambios**.

### 5.4 Eliminar un cliente

1. En el listado, pulsa **Eliminar**.
2. Confirma en la ventana de confirmación pulsando **Sí, eliminar**.

> Eliminar un cliente no borra sus ventas anteriores; estas quedan sin cliente asignado.

### 5.5 Buscar y exportar

- Usa el cuadro de **búsqueda** para localizar clientes por nombre o NIT.
- Pulsa **Exportar PDF** o **Exportar Excel** para descargar el listado actual.

---

## 6. Productos

El módulo de **Productos** administra el catálogo, el stock y las fechas de vencimiento.

### 6.1 Crear un producto

1. Entra a **Productos** y pulsa **Nuevo producto** (o **Crear**).
2. Completa el formulario:
   - **Nombre** *(obligatorio)*.
   - **Código (SKU)** *(opcional)*: código interno de referencia.
   - **Costo de producción** *(obligatorio)*: costo unitario del producto.
   - **Precio de venta** *(obligatorio)*.
   - **Stock** *(obligatorio)*: cantidad actual en inventario.
   - **Stock mínimo** *(obligatorio)*: nivel bajo el cual se mostrará la alerta de stock bajo.
   - **Fecha de vencimiento** *(opcional)*: para productos caducables.
   - **Producto activo**: si está marcado, el producto puede venderse. Desmárcalo para
     deshabilitar su venta sin eliminarlo.
   - **Descripción** *(opcional)*.
3. Pulsa **Guardar**.

> El margen de cada producto (diferencia entre precio de venta y costo) se calcula automáticamente
> y aparece en el listado y en los reportes.

### 6.2 Ver un producto

Pulsa sobre el producto (o **Ver**) para consultar sus datos, movimientos de inventario y su
participación en ventas.

### 6.3 Editar y eliminar

- **Editar**: cambia los campos necesarios y pulsa **Guardar cambios**.
- **Eliminar**: pulsa **Eliminar** y confirma con **Sí, eliminar**.

> Si el producto ya fue vendido, no se puede eliminar; desactívalo marcando la opción
> **Producto activo** para dejar de ofrecerlo.

### 6.4 Stock y alertas

En el listado cada producto muestra una insignia de estado:

- **Disponible**: stock por encima del mínimo.
- **Stock bajo**: stock en o por debajo del mínimo.
- **Agotado**: sin stock.
- **Por vencer**: fecha de vencimiento próxima.

El panel de control resume estas alertas agrupadas.

### 6.5 Buscar y exportar

- Usa la **búsqueda** para localizar productos por nombre, SKU o descripción.
- Utiliza los filtros para consultar **todos**, **agotados**, **stock bajo** o **por vencer**.
- Pulsa **Exportar PDF** o **Exportar Excel** para descargar el listado.

---

## 7. Ventas

El módulo de **Ventas** permite registrar facturas con ítems dinámicos y actualización
automática del stock.

### 7.1 Crear una venta

1. Entra a **Ventas** y pulsa **Nueva venta** (o **Crear**).
2. Completa los datos generales:
   - **Cliente** *(opcional)*: selecciona un cliente o déjalo vacío para una venta sin
     factura a cliente.
   - **Vendedor** *(obligatorio)*: quien vende. Por defecto eres tú.
   - **Notas** *(opcional)*.
3. Añade los ítems de la venta:
   - Pulsa **Agregar ítem**.
   - Selecciona el **producto**; el precio unitario y el total de la línea se calculan solos.
   - Escribe la **cantidad**; el sistema valida que no supere el stock disponible.
   - Repite para cada producto. Pulsa el ícono de papelera para quitar un ítem.
4. Revisa los totales automáticos: **subtotal**, **IVA** (porcentaje configurado) y **total**.
5. Pulsa **Guardar**.

Al guardar:

- Se genera el **número de factura** automáticamente.
- El **stock** de cada producto se descuenta automáticamente.
- Se registra un **movimiento de salida** de inventario.
- La venta queda con estado **pendiente** (no pagada).

### 7.2 Ver una factura

Desde el listado de ventas, pulsa sobre el número de factura (o **Ver**). La ficha muestra:

- Número de factura, fecha, cliente y vendedor.
- Lista de productos, cantidades, precios unitarios y totales.
- Subtotal, IVA y total.
- Notas (si existen).
- Estado: **Pagada** o **Pendiente**.

### 7.3 Marcar como pagada / pendiente

Las ventas que se registran como crédito quedan **pendientes**. Cuando el cliente pague:

1. Abre la venta.
2. Pulsa el botón **Página** (o **Marcar como pagada**).
3. El estado cambia a **Pagada**.

Si fue un error, puedes volver a marcarla como **pendiente** con el mismo botón.

> Solo los roles **Administrador** y **Encargado** pueden cambiar el estado de pago.

### 7.4 Editar una venta (solo admin)

1. Abre la venta y pulsa **Editar**.
2. Ajusta los datos o los ítems (productos, cantidades). El sistema recalcula totales y
   **concilia el stock** con las cantidades originales.
3. Pulsa **Guardar cambios**.

### 7.5 Anular / eliminar una venta (solo admin)

1. Abre la venta y pulsa **Eliminar**.
2. Confirma con **Sí, eliminar**.

Al eliminar una venta, el stock de los productos se **devuelve** automáticamente.

### 7.6 Exportar

- **Factura PDF**: dentro de la ficha de la venta, pulsa **Exportar PDF** para descargar la
  factura lista para imprimir o enviar.
- **Listado PDF / Excel**: en la página de **Ventas**, con la búsqueda y filtros aplicados,
  pulsa **Exportar PDF** o **Exportar Excel** para descargar el listado.

### 7.7 Buscar y filtrar

El listado permite:

- **Buscar** por número de factura, cliente o notas.
- **Filtrar por vendedor** y por **rango de fechas**.

---

## 8. Inventario

El módulo de **Inventario** registra todas las entradas y salidas de productos y ajusta el
stock automáticamente.

> Disponible para los roles **Administrador** y **Encargado**.

### 8.1 Registrar un movimiento

1. Entra a **Inventario** y pulsa **Nuevo movimiento** (o **Crear**).
2. Completa el formulario:
   - **Producto** *(obligatorio)*.
   - **Tipo** *(obligatorio)*: **Entrada** (aumenta stock) o **Salida** (disminuye stock).
   - **Cantidad** *(obligatorio)*: número de unidades.
   - **Motivo** *(obligatorio)*: razón del movimiento (ej. compra a proveedor, merma, uso
     interno).
   - **Referencia** *(opcional)*: número de documento o referencia (ej. factura del proveedor).
3. Pulsa **Guardar**.

El stock del producto se actualiza automáticamente y el movimiento queda registrado en el
listado con su fecha, tipo, entrada/salida y saldo resultante.

> Las ventas también generan movimientos de salida automáticamente. Cuando editas o anulas
> una venta, se registran los movimientos correctivos correspondientes.

### 8.2 Consultar el historial y exportar

- Filtra por **producto** y por **rango de fechas**.
- Pulsa **Exportar PDF** o **Exportar Excel** para descargar el reporte de movimientos.

---

## 9. Reportes

El módulo de **Reportes** te permite analizar el negocio y exportar información. Disponible
para los roles **Administrador** y **Encargado**.

### 9.1 Tipos de reporte

Hay cuatro pestañas:

- **Ventas**: listado de ventas con cliente, vendedor, fechas, montos y estado de pago.
- **Inventario**: resumen por producto: entradas, salidas y saldo neto.
- **Clientes**: por cliente: cantidad de ventas y total comprado.
- **Productos**: por producto: unidades vendidas, ingresos y margen.

En la parte superior se muestran **métricas globales**: total de ventas, productos, clientes
y el valor del inventario.

### 9.2 Filtros

Según el tipo de reporte, puedes filtrar por:

- **Fecha desde / fecha hasta** (todos los tipos).
- **Cliente** (ventas y clientes).
- **Producto** (inventario y productos).
- **Vendedor** y **estado de pago** (ventas).

1. Selecciona el tipo de reporte en las pestañas.
2. Aplica los filtros que necesites.
3. Pulsa **Aplicar**. El reporte se actualiza al instante.

### 9.3 Exportar

Una vez aplicados los filtros:

- **Exportar PDF**: descarga el reporte en formato PDF (para imprimir o archivar).
- **Exportar Excel**: descarga el reporte en formato Excel (para analizar los datos).

El archivo descargado respeta los filtros aplicados.

---

## 10. Configuración

El módulo de **Configuración** (solo **Administrador**) define los datos de la empresa y las
preferencias del sistema.

### 10.1 Datos de la empresa

- **Nombre de la empresa** *(obligatorio)*: aparece en la barra superior y en los documentos.
- **Eslogan**: frase corta bajo el nombre.
- **Logotipo**: imagen del logotipo (formatos PNG, JPG, WEBP o SVG). Se muestra en la barra
  lateral y en la pantalla de inicio de sesión.

### 10.2 Datos de contacto

Los datos que se muestran en las facturas y reportes:

- **NIT**
- **Correo electrónico**
- **Teléfono**
- **Dirección**

### 10.3 Preferencias

- **Moneda**: Quetzales (GTQ) o Dólares (USD). Define el símbolo usado en los montos.
- **IVA (%)**: porcentaje aplicado a las ventas (por defecto 12).
- **Idioma por defecto**: idioma en el que se abre el sistema para usuarios nuevos.

> Cambiar el idioma por defecto no afecta el idioma que cada usuario eligió en su cuenta.

Para guardar los cambios, pulsa **Guardar cambios** al final del formulario.

---

## 11. Auditoría

El módulo de **Auditoría** (solo **Administrador**) registra cada alta, modificación o baja
realizada en el sistema, para saber quién hizo qué y cuándo.

### 11.1 El registro

Cada entrada muestra:

- **Fecha** y hora.
- **Usuario** que realizó la acción.
- **Acción**: Creado, Modificado o Eliminado.
- **Modelo** (ej. Cliente, Producto, Venta).
- **Registro** afectado (con acceso directo si aún existe).
- **IP** desde la que se realizó.

### 11.2 Filtrar

Puedes filtrar por:

- **Modelo** (todos o uno específico).
- **Acción** (creado, modificado, eliminado).
- **Usuario**.
- **Rango de fechas**.

Pulsa **Aplicar** para filtrar o **Reiniciar** para quitar los filtros.

### 11.3 Ver el detalle

Pulsa **Ver** en cualquier registro para consultar el detalle completo, que incluye los
**campos que cambiaron** (valor anterior → valor nuevo) cuando la acción fue una modificación.

> Los registros de auditoría no se pueden borrar desde la aplicación.

---

## 12. Usuarios

El módulo de **Usuarios** (solo **Administrador**) administra el acceso al sistema.

### 12.1 Crear un usuario

1. Entra a **Usuarios** y pulsa **Nuevo usuario** (o **Crear**).
2. Completa el formulario:
   - **Nombre** *(obligatorio)*.
   - **Correo electrónico** *(obligatorio)*: será la identificación para iniciar sesión.
   - **Rol** *(obligatorio)*: Administrador, Vendedor o Encargado.
   - **Idioma** *(obligatorio)*: idioma por defecto de la interfaz para este usuario.
   - **Contraseña** *(obligatoria al crear)*.
3. Pulsa **Guardar**.

> El sistema no tiene registro público: todos los usuarios se crean aquí.

### 12.2 Editar un usuario

1. En el listado, localiza el usuario y pulsa **Editar**.
2. Cambia nombre, correo, rol, idioma o contraseña.
3. Si no quieres cambiar la contraseña, deja el campo **vacío** (solo se reemplaza si escribes
   una nueva).
4. Pulsa **Guardar cambios**.

### 12.3 Eliminar un usuario

1. Pulsa **Eliminar** en el usuario correspondiente.
2. Confirma con **Sí, eliminar**.

> Cuidado: eliminar un usuario quita su acceso. Las ventas que realizó conservan su nombre como
> vendedor histórico.

---

## 13. Roles y permisos

| Capacidad | Administrador | Vendedor | Encargado |
|-----------|:-----------:|:---------:|:---------:|
| Ver panel de control | ✔ | ✔ | ✔ |
| Clientes (crear/ver/editar/eliminar) | ✔ | ✔ | ✔ |
| Clientes (exportar PDF/Excel) | ✔ | ✔ | ✔ |
| Productos (ver) | ✔ | ✔ | ✔ |
| Productos (crear/editar/eliminar) | ✔ | ✘ | ✔ |
| Ventas (crear) | ✔ | ✔ | ✘ |
| Ventas (ver listado y factura) | ✔ | ✔ | ✔ |
| Ventas (marcar pagada/pendiente) | ✔ | ✘ | ✔ |
| Ventas (editar/anular) | ✔ | ✘ | ✘ |
| Inventario (movimientos y exportar) | ✔ | ✘ | ✔ |
| Reportes (consultar y exportar) | ✔ | ✘ | ✔ |
| Configuración | ✔ | ✘ | ✘ |
| Auditoría | ✔ | ✘ | ✘ |
| Usuarios (CRUD) | ✔ | ✘ | ✘ |

---

## 14. Preguntas frecuentes

**¿No veo algunos módulos en el menú?**
Es normal: el menú se adapta a tu rol. Si necesitas acceso a otro módulo, un administrador debe
cambiar tu rol en **Usuarios**.

**¿Cómo cambio el idioma de la aplicación?**
Pulsa el ícono de **globo** en la barra superior y elige Español o English. Tu elección se
aplica de inmediato. También puedes asignar un idioma por usuario (desde **Usuarios** en el rol
administrador) y un idioma por defecto en **Configuración**.

**¿Cómo enciendo el modo oscuro?**
Pulsa el ícono de **sol/luna** en la barra superior. La preferencia se guarda en tu navegador.

**¿Olvidé mi contraseña?**
Desde la pantalla de inicio de sesión pulsa **¿Olvidaste tu contraseña?** y sigue el enlace que
recibirás por correo.

**¿Una venta pendiente significa que debo cobrar al cliente?**
Sí. Al registrar una venta, el estado inicial es **pendiente**. Marca la venta como **pagada**
cuando el cliente pague. Las ventas pendientes con cliente suman en **cuentas por cobrar** del
panel de control.

**¿Qué pasa si vendo más de una unidad de un producto que está agotado?**
El sistema valida la cantidad contra el stock disponible y no permite vender más de lo que hay.
Primero registra una **entrada de inventario** para reponer existencias.

**¿Por qué no puedo eliminar un producto?**
Si un producto ya tiene ventas, no se puede eliminar. En su lugar, desmárcalo como **producto
activo** para dejar de venderlo.

**¿Los reportes respetan mis filtros al exportar?**
Sí. Los archivos PDF y Excel se generan con exactamente los mismos filtros que ves en pantalla.

---

© AS-NegocioOS. Documento de uso interno.