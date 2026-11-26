# MIG-02 · Fixtures de catálogo (sync)

Sin datos reales de tienda ni tokens. Forma según secciones Product/Category de [MIG-01-matriz-compatibilidad.md](./MIG-01-matriz-compatibilidad.md). Los tests Feature cargan estos bloques JSON por encabezado `###`.

## Casos mínimos

| ID fixture | Descripción | Variantes | Categorías | Imágenes | Notas |
|------------|-------------|-----------|------------|----------|-------|
| `prod-simple` | Producto simple publicado | 1 | 1 | 1 | stock plano `""` |
| `prod-multi` | Multivariante (2+ values) | 3 | 2 | 3 | attributes + values localizados |
| `prod-i18n` | Nombre/descripción ES + EN | 1 | 0 | 0 | LocalizedString; sin clave images/categories |
| `prod-nulls` | SEO/tags/video vacíos | 1 | 0 | 0 | null vs omitido (`canonical_url` omitido) |
| `prod-multi-inventory` | Niveles A=3 B=7 | 1 | 1 | 0 | IDs de ubicación opacos; `stock` total 10 |

## Payloads (JSON anonimizado)

### prod-simple

```json
{
  "id": 100,
  "name": {"es": "Perfume Demo"},
  "description": {"es": "<p>Demo</p>"},
  "handle": {"es": "perfume-demo"},
  "brand": "Gelia",
  "published": true,
  "visibility": "visible",
  "free_shipping": false,
  "requires_shipping": true,
  "video_url": null,
  "seo_title": {"es": "Perfume Demo SEO"},
  "seo_description": {"es": "SEO desc"},
  "tags": "demo,test",
  "attributes": [],
  "canonical_url": "https://cdn.example.test/products/perfume-demo",
  "categories": [10],
  "images": [
    {
      "id": 500,
      "src": "https://cdn.example.test/demo.jpg",
      "position": 1,
      "alt": {"es": "Demo alt"}
    }
  ],
  "variants": [
    {
      "id": 900,
      "sku": "SKU-DEMO-1",
      "price": "199.00",
      "promotional_price": null,
      "cost": "80.50",
      "stock": "",
      "stock_management": false,
      "values": [],
      "barcode": "7500000000000",
      "weight": "0.200"
    }
  ]
}
```

### prod-multi

```json
{
  "id": 200,
  "name": {"es": "Kit tres tamaños"},
  "description": {"es": "<p>Multi</p>"},
  "handle": {"es": "kit-tres-tamanos"},
  "brand": {"es": "Gelia"},
  "published": true,
  "visibility": "visible",
  "attributes": [{"es": "Tamaño", "en": "Size"}],
  "categories": [10, 20],
  "images": [
    {"id": 501, "src": "https://cdn.example.test/a.jpg", "position": 1, "alt": {"es": "A"}},
    {"id": 502, "src": "https://cdn.example.test/b.jpg", "position": 2, "alt": {"es": "B"}},
    {"id": 503, "src": "https://cdn.example.test/c.jpg", "position": 3, "alt": {"es": "C"}}
  ],
  "variants": [
    {
      "id": 901,
      "sku": "SKU-M-30",
      "price": "120.00",
      "promotional_price": "99.90",
      "cost": "40.00",
      "stock": 5,
      "stock_management": true,
      "values": [{"es": "30 ml", "en": "30 ml"}],
      "barcode": "7500000000001",
      "weight": "0.080"
    },
    {
      "id": 902,
      "sku": "SKU-M-50",
      "price": "180.00",
      "promotional_price": null,
      "cost": "55.00",
      "stock": 3,
      "stock_management": true,
      "values": [{"es": "50 ml", "en": "50 ml"}],
      "barcode": "7500000000002",
      "weight": "0.120"
    },
    {
      "id": 903,
      "sku": "SKU-M-100",
      "price": "250.00",
      "promotional_price": null,
      "cost": "80.00",
      "stock": 1,
      "stock_management": true,
      "values": [{"es": "100 ml", "en": "100 ml"}],
      "barcode": "7500000000003",
      "weight": "0.200"
    }
  ]
}
```

### prod-i18n

```json
{
  "id": 300,
  "name": {"es": "Aroma noche", "en": "Night scent"},
  "description": {"es": "<p>ES</p>", "en": "<p>EN</p>"},
  "handle": {"es": "aroma-noche", "en": "night-scent"},
  "published": true,
  "variants": [
    {
      "id": 910,
      "sku": "SKU-I18N",
      "price": "10.00",
      "stock": 2,
      "values": []
    }
  ]
}
```

### prod-nulls

```json
{
  "id": 400,
  "name": {"es": "Sin SEO"},
  "handle": {"es": "sin-seo"},
  "published": false,
  "visibility": "hidden",
  "seo_title": null,
  "seo_description": null,
  "tags": null,
  "video_url": null,
  "images": [],
  "variants": [
    {
      "id": 920,
      "sku": "SKU-NULLS",
      "price": "1.00",
      "stock": null
    }
  ]
}
```

### cat-arbol

```json
[
  {
    "id": 10,
    "name": {"es": "Aromas"},
    "handle": {"es": "aromas"},
    "description": {"es": "Raíz"},
    "parent": null,
    "seo_title": {"es": "SEO Aromas"},
    "seo_description": {"es": "Desc SEO Aromas"}
  },
  {
    "id": 20,
    "name": {"es": "Florales"},
    "handle": {"es": "florales"},
    "description": {"es": "Hija"},
    "parent": 10,
    "seo_title": {"es": "SEO Florales"},
    "seo_description": {"es": "Desc SEO Florales"}
  }
]
```

### loc-dos

```json
[
  {
    "id": "01GQ2ZHK064BQRHGDB7CCV0Y6N",
    "name": {"es": "Ubicación A"},
    "is_default": true,
    "priority": 0,
    "tags": ["online"]
  },
  {
    "id": "01GQ2ZHK064BQRHGDB7DDCS4SA",
    "name": {"es": "Ubicación B"},
    "is_default": false,
    "priority": 1,
    "tags": ["offline"]
  }
]
```

### prod-multi-inventory

```json
{
  "id": 500,
  "name": {"es": "Stock por ubicación"},
  "handle": {"es": "stock-ubicacion"},
  "published": true,
  "visibility": "visible",
  "categories": [10],
  "images": [],
  "variants": [
    {
      "id": 950,
      "sku": "SKU-INV-1",
      "price": "10.00",
      "stock": 10,
      "stock_management": true,
      "values": [],
      "inventory_levels": [
        {"location_id": "01GQ2ZHK064BQRHGDB7CCV0Y6N", "stock": 3},
        {"location_id": "01GQ2ZHK064BQRHGDB7DDCS4SA", "stock": 7}
      ]
    }
  ]
}
```

**Usado por:** tests `TiendanubeCatalogoSyncTest`, [MIG-02-comparacion-sync.md](./MIG-02-comparacion-sync.md), MIG-06.
