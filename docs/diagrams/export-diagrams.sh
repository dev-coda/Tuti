#!/bin/bash

# Script para exportar los diagramas Mermaid a diferentes formatos
# Requiere: npm install -g @mermaid-js/mermaid-cli

echo "🚀 Exportando diagramas del sistema Tuti..."

# Crear directorio de salida
mkdir -p exports

# Exportar diagrama ER a diferentes formatos
echo "📊 Exportando diagrama de base de datos..."
mmdc -i database-er-diagram.mmd -o exports/database-er-diagram.png -w 1920 -H 1080
mmdc -i database-er-diagram.mmd -o exports/database-er-diagram.svg
mmdc -i database-er-diagram.mmd -o exports/database-er-diagram.pdf

# Exportar diagrama de arquitectura a diferentes formatos
echo "🏗️  Exportando diagrama de arquitectura..."
mmdc -i system-architecture-diagram.mmd -o exports/system-architecture-diagram.png -w 1920 -H 1080
mmdc -i system-architecture-diagram.mmd -o exports/system-architecture-diagram.svg
mmdc -i system-architecture-diagram.mmd -o exports/system-architecture-diagram.pdf

echo "✅ Diagramas exportados exitosamente en la carpeta 'exports':"
ls -la exports/

echo ""
echo "📁 Archivos generados:"
echo "   - database-er-diagram.png/svg/pdf"
echo "   - system-architecture-diagram.png/svg/pdf"
echo ""
echo "💡 Tip: Los archivos PNG son ideales para README y documentación"
echo "💡 Tip: Los archivos SVG son escalables y perfectos para web"
echo "💡 Tip: Los archivos PDF son ideales para documentos formales"
