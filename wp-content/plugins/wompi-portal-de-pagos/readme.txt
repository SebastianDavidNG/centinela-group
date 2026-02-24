=== Wompi Portal de Pagos ===

Contributors: Wompi
Tags: Wompi, pasarela de pagos, portal de pagos, Bancolombia, link de pago
Requires at least: 3.5.0
Tested up to: 6.7
Stable tag: 2.0.0
Requires PHP: 7.2
License: GPL-3.0
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Pasarela de Pago de WooCommerce para Wompi

== Description ==

Recibe pagos en línea integrando el Widget de Wompi y permite aceptar pagos con cualquiera de los métodos de pago disponibles para tu negocio.

=== Dependencia de Servicios de Terceros ===

Este plugin depende de los servicios de Wompi para procesar pagos. Wompi maneja de manera segura todos los procesos de pago, incluyendo transacciones con tarjetas de crédito, tarjetas de débito y otros métodos de pago locales.

=== Servicios Utilizados ===

- **URL de Checkout**: [https://checkout.wompi.co](https://checkout.wompi.co)
- **Endpoint de Producción**: [https://production.wompi.co/v1](https://production.wompi.co/v1)
- **Endpoint de Sandbox (Pruebas)**: [https://sandbox.wompi.co/v1](https://sandbox.wompi.co/v1)
- **Vista de Transacciones**: [https://wompi.com/es/co/transacciones](https://wompi.com/es/co/transacciones)

=== Transmisión de Datos ===

Este plugin envía datos de transacciones y pagos a los servidores de Wompi para procesar compras de manera segura. No se almacena información sensible del cliente dentro del plugin ni en los servidores de tu tienda.

=== Términos y Políticas de Wompi ===

Para más información sobre los términos de uso y políticas de privacidad de Wompi, por favor consulta los siguientes enlaces:

- [**Términos de Servicio**](https://wompi.com/es/co/reglamento-comercios/)
- [**Política de Privacidad**](https://wompi.com/es/co/politica-de-privacidad)

= Características =

* Puedes ser parte de Wompi como individuo o empresa, sin importar el tamaño de tu negocio y tu capacidad tecnológica: ¡nuestra pasarela ayuda a aumentar las ventas de emprendimientos, startups y grandes marcas!
* En Wompi encuentras los principales medios de pago electrónico del mercado: tarjetas de débito, tarjetas de crédito, transferencias y efectivo. Además, te ofrecemos tres métodos de pago propios: Botón Bancolombia, Nequi y Corresponsales Bancarios Bancolombia.
* Puedes integrar estos métodos de pago a sitios web, aplicaciones móviles, redes sociales, WhatsApp y en tiendas físicas.
* Integra el Widget de Wompi y permite aceptar pagos con cualquiera de los métodos de pago disponibles para tu negocio.
* Gratis para usar – [Licencia de código abierto GPL-3.0 en GitHub](https://github.com/wompi-co/plugin-woocommerce)

== Installation ==

La documentación sobre cómo instalar el plugin con la pasarela de pago de Wompi se encuentra [**Aquí**](https://docs.wompi.co/docs/colombia/woocommerce-wordpress-plugin/) donde se detallan los requisitos, funcionalidades y su licencia.

== Frequently Asked Questions ==

- **¿Cómo puedo configurar los ajustes del plugin?**

En WooCommerce -> Plugins -> Wompi Portal de Pagos -> Configuración: Se debe seleccionar la opción Habilitar Wompi. Adicionalmente, debes tener a mano las siguientes llaves: Llave pública de prueba, Llave privada de prueba, Llave privada de eventos de prueba, Llave de integridad de prueba, Llave pública de producción, Llave privada de producción, Llave privada de eventos de producción, Llave de Integridad de Producción. Las cuales deben ser ingresadas en sus respectivos campos.

- **¿Dónde puedo obtener los datos de configuración de mi negocio?**

Para usar Wompi necesitas un par de llaves de autenticación asociadas a tu negocio. Puedes leer toda la información sobre llaves de autenticación y entornos de ejecución en el siguiente enlace https://docs.wompi.co/docs/colombia/ambientes-y-llaves/
Si aún no tienes estas llaves, regístrate en comercials.wompi.co y obtén tu par de llaves Wompi, para que puedas empezar a integrar tu negocio.

- **¿Qué configuraciones de tienda son compatibles?**

Wompi actualmente soporta transacciones en COP (Pesos Colombianos).

- **¿Cuáles son los estados que puede tener una transacción?**

El estado de una transacción representa en qué parte del proceso de pago se encuentra. El estado permite saber si la transacción aún está en proceso (**PENDIENTE**) o si ya ha alcanzado un estado final.
El estado final de una transacción es uno de los siguientes:

**APROBADA:** Transacción aprobada.
**RECHAZADA:** Transacción rechazada.
**ANULADA:** Transacción cancelada (solo aplica para transacciones con tarjeta).
**ERROR:** Error interno del respectivo método de pago.

- **¿Cómo puedo contribuir al plugin?**

Puedes contribuir al plugin traduciendo lo. Simplemente visita [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/wompi-portal-de-pagos/) para empezar.

== Screenshots ==

1. Conoce más de wompi.
2. Encuentra la seguridad que buscas.
3. Nuestros Aliados.
4. Nuestro Respaldo.