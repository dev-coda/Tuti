# Diagramas del Sistema Tuti

Esta carpeta contiene los diagramas del sistema en formato Mermaid.

## Archivos Disponibles

### 1. `database-er-diagram.mmd`

**Diagrama de Relaciones Entre Entidades (ER)**

-   Muestra todas las tablas de la base de datos
-   Campos principales de cada entidad
-   Relaciones entre tablas (1:1, 1:N, N:M)
-   Claves primarias y foráneas

### 2. `system-architecture-diagram.mmd`

**Diagrama de Arquitectura del Sistema**

-   Arquitectura en capas completa
-   Flujo de datos desde frontend hasta base de datos
-   Servicios externos integrados
-   Sistema de colas y jobs
-   Tipos de usuarios y accesos

## Cómo Usar Estos Archivos

### Opción 1: Mermaid Live Editor (Recomendado)

1. Ve a https://mermaid.live/
2. Copia el contenido de cualquiera de los archivos `.mmd`
3. Pégalo en el editor
4. Exporta como PNG, SVG o PDF

### Opción 2: VS Code con Extensión Mermaid

1. Instala la extensión "Mermaid Preview" en VS Code
2. Abre cualquier archivo `.mmd`
3. Usa Ctrl+Shift+P y busca "Mermaid: Preview"
4. Exporta desde la vista previa

### Opción 3: Mermaid CLI

```bash
# Instalar Mermaid CLI
npm install -g @mermaid-js/mermaid-cli

# Exportar a PNG
mmdc -i database-er-diagram.mmd -o database-er-diagram.png

# Exportar a SVG
mmdc -i system-architecture-diagram.mmd -o system-architecture-diagram.svg
```

### Opción 4: Herramientas Online Alternativas

-   **Draw.io/Diagrams.net**: Importa código Mermaid
-   **Kroki**: https://kroki.io/
-   **Mermaid Chart**: https://www.mermaidchart.com/

## Formatos de Exportación Recomendados

-   **PNG**: Para documentación, presentaciones, README
-   **SVG**: Para uso web, escalable
-   **PDF**: Para documentos formales
-   **HTML**: Para documentación interactiva

## Notas Técnicas

-   Los archivos están en formato Mermaid v9+
-   Compatible con la mayoría de editores de Mermaid
-   Los colores y estilos están incluidos en el código
-   Optimizados para legibilidad en diferentes tamaños
