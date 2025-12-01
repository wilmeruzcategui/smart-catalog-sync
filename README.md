# Smart Catalog Sync

**Version:** 1.0.0
**Autor:** Wilmer Uzcategui
**Requiere WordPress:** 5.8 o superior
**Requiere WooCommerce:** 5.0 o superior
**Requiere PHP:** 7.4 o superior
**Licencia:** GPL v2 o posterior

## Descripción

Smart Catalog Sync es un plugin de WordPress que sincroniza automáticamente tu catálogo de productos de WooCommerce con sistemas externos mediante JSON, optimizado especialmente para alimentar sistemas de inteligencia artificial con datos actualizados de inventario, precios y disponibilidad.

## Características Principales

- **Sincronización Automática**: Configura intervalos personalizados (desde cada 15 minutos hasta diariamente)
- **Sincronización Manual**: Botón para sincronizar instantáneamente cuando lo necesites
- **Interfaz Moderna**: Dashboard hermoso y fácil de usar con estadísticas en tiempo real
- **Formato JSON Optimizado para IA**: Estructura de datos diseñada para ser fácilmente interpretada por sistemas de IA
- **Datos Completos**: Incluye precios, stock, descripciones, imágenes, categorías, variaciones y más
- **Test de Conexión**: Verifica que tu endpoint está funcionando correctamente antes de sincronizar
- **Opciones Configurables**: Elige qué datos incluir (imágenes, variaciones, categorías)
- **Cron Automático**: Utiliza el sistema de cron de WordPress para sincronizaciones programadas
- **Seguro y Eficiente**: Código optimizado y siguiendo las mejores prácticas de WordPress

## Instalación

### Método 1: Instalación Manual

1. Descarga el plugin y descomprime el archivo
2. Sube la carpeta `smart-catalog-sync` a `/wp-content/plugins/`
3. Activa el plugin desde el menú "Plugins" en WordPress
4. Ve a "Catalog Sync" en el menú lateral de administración

### Método 2: Desde el Directorio de GitHub

```bash
cd wp-content/plugins
git clone https://github.com/wilmeruzcategui/smart-catalog-sync.git
```

Luego activa el plugin desde WordPress.

## Configuración

1. **Navega a "Catalog Sync"** en el menú de administración de WordPress
2. **Configura la URL de destino**: Ingresa la URL donde deseas recibir los datos JSON
3. **Prueba la conexión**: Haz clic en "Probar Conexión" para verificar que tu endpoint responde
4. **Selecciona la frecuencia**: Elige cada cuánto tiempo quieres sincronizar
5. **Activa la sincronización automática**: Marca la casilla para habilitar el cron
6. **Configura las opciones de datos**: Elige si incluir imágenes, variaciones y categorías
7. **Guarda la configuración**: Haz clic en "Guardar Configuración"

### Opciones de Frecuencia

- Cada 15 minutos
- Cada 30 minutos
- Cada hora
- Dos veces al día
- Una vez al día

## Formato de Datos JSON

Los productos se envían en el siguiente formato:

```json
{
  "sync_date": "2025-12-01T10:30:00Z",
  "store_info": {
    "name": "Mi Tienda",
    "url": "https://mitienda.com",
    "currency": "USD",
    "currency_symbol": "$"
  },
  "total_products": 100,
  "products": [
    {
      "id": 123,
      "name": "Producto Ejemplo",
      "slug": "producto-ejemplo",
      "sku": "PROD-001",
      "type": "simple",
      "status": "publish",
      "permalink": "https://mitienda.com/producto/producto-ejemplo",
      "price": 99.99,
      "regular_price": 129.99,
      "sale_price": 99.99,
      "on_sale": true,
      "stock_status": "instock",
      "stock_quantity": 50,
      "manage_stock": true,
      "in_stock": true,
      "backorders_allowed": false,
      "description": "Descripción completa del producto...",
      "short_description": "Descripción corta...",
      "categories": [
        {
          "id": 5,
          "name": "Electrónica",
          "slug": "electronica"
        }
      ],
      "tags": ["nuevo", "oferta", "destacado"],
      "images": [
        "https://mitienda.com/wp-content/uploads/imagen1.jpg",
        "https://mitienda.com/wp-content/uploads/imagen2.jpg"
      ],
      "featured_image": "https://mitienda.com/wp-content/uploads/imagen1.jpg",
      "attributes": [
        {
          "name": "Color",
          "visible": true,
          "variation": true,
          "options": ["Rojo", "Azul", "Verde"]
        }
      ],
      "variations": [
        {
          "id": 124,
          "sku": "PROD-001-RED",
          "price": 99.99,
          "regular_price": 129.99,
          "sale_price": 99.99,
          "stock_quantity": 20,
          "stock_status": "instock",
          "in_stock": true,
          "attributes": {
            "color": "Rojo"
          },
          "image": "https://mitienda.com/wp-content/uploads/rojo.jpg"
        }
      ],
      "weight": "0.5",
      "dimensions": {
        "length": "10",
        "width": "5",
        "height": "2"
      },
      "rating_count": 25,
      "average_rating": 4.5,
      "total_sales": 150
    }
  ]
}
```

## Uso con IA

Este plugin está diseñado para facilitar la integración con sistemas de IA. Los datos JSON pueden ser:

1. **Guardados en un archivo**: Recibe el JSON y guárdalo en un archivo `.json` para que tu IA lo lea
2. **Almacenados en Google Sheets**: Usa servicios como Zapier o Make para enviar los datos a una hoja de cálculo
3. **Procesados directamente**: Integra con tu API de IA para actualizar el contexto en tiempo real
4. **Vectorizados**: Convierte la información en embeddings para búsqueda semántica

### Ejemplo de Endpoint Receptor (Node.js)

```javascript
const express = require('express');
const fs = require('fs');
const app = express();

app.use(express.json({ limit: '50mb' }));

app.post('/api/productos', (req, res) => {
    // Guardar en archivo JSON
    fs.writeFileSync('productos.json', JSON.stringify(req.body, null, 2));

    console.log(`Recibidos ${req.body.total_products} productos`);

    res.status(200).json({
        success: true,
        message: 'Productos sincronizados exitosamente'
    });
});

app.listen(3000, () => {
    console.log('Servidor escuchando en puerto 3000');
});
```

### Ejemplo de Endpoint Receptor (Python/Flask)

```python
from flask import Flask, request, jsonify
import json

app = Flask(__name__)

@app.route('/api/productos', methods=['POST'])
def recibir_productos():
    data = request.get_json()

    # Guardar en archivo JSON
    with open('productos.json', 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False, indent=2)

    print(f"Recibidos {data['total_products']} productos")

    return jsonify({
        'success': True,
        'message': 'Productos sincronizados exitosamente'
    }), 200

if __name__ == '__main__':
    app.run(port=3000)
```

## Preguntas Frecuentes

### ¿Necesito conocimientos técnicos para usar este plugin?

No, la interfaz es muy intuitiva. Solo necesitas configurar la URL de destino y activar la sincronización.

### ¿Afecta el rendimiento de mi sitio?

No, las sincronizaciones se ejecutan en segundo plano usando el cron de WordPress, sin afectar la experiencia de tus visitantes.

### ¿Qué pasa si mi endpoint no está disponible?

El plugin intentará sincronizar en el próximo intervalo programado. Los errores se registran en el log de WordPress.

### ¿Puedo sincronizar solo cuando actualizo un producto?

Actualmente el plugin sincroniza todos los productos en cada intervalo. Las sincronizaciones por producto individual están planificadas para futuras versiones.

### ¿Es seguro enviar mis datos?

El plugin envía los datos via HTTPS POST. Asegúrate de que tu endpoint también use HTTPS para máxima seguridad.

## Soporte

Para reportar bugs o solicitar nuevas características:
- GitHub Issues: https://github.com/wilmeruzcategui/smart-catalog-sync/issues

## Changelog

### 1.0.0 (2025-12-01)
- Lanzamiento inicial
- Sincronización automática programable
- Sincronización manual
- Test de conexión
- Interfaz de administración moderna
- Formato JSON optimizado para IA
- Soporte para variaciones de productos
- Inclusión de imágenes, categorías y tags
- Sistema de estadísticas en tiempo real

## Licencia

Este plugin es software libre; puedes redistribuirlo y/o modificarlo bajo los términos de la GNU General Public License versión 2 o posterior.

## Créditos

Desarrollado con por Wilmer Uzcategui
