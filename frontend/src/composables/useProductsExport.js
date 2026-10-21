import { jsPDF } from 'jspdf'
import autoTable from 'jspdf-autotable'
import ExcelJS from 'exceljs'
import api from '@/service/api'

// La API pagina (máximo 50 por página), así que recorremos todas las páginas
export const fetchAllProducts = async () => {
  const todos = []
  let page = 1
  let ultima = 1
  do {
    const { data } = await api.get('/products', { params: { page, per_page: 50 } })
    todos.push(...data.data)
    ultima = data.last_page
    page++
  } while (page <= ultima)
  return todos
}

const descargar = (blob, nombre) => {
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = nombre
  a.click()
  URL.revokeObjectURL(url)
}

// Evita que Excel interprete un texto escrito por un usuario como fórmula (=, +, -, @)
const seguro = (valor) => {
  const texto = String(valor ?? '')
  return /^[=+\-@\t\r]/.test(texto) ? `'${texto}` : texto
}

const ENCABEZADOS = ['#', 'Nombre', 'Descripción', 'Precio', 'Stock']

// ---------------------------------------------------------------------
// CSV
// ---------------------------------------------------------------------
export const exportCsv = (productos) => {
  const texto = (v) => `"${seguro(v).replace(/"/g, '""')}"`

  const filas = productos.map((p, i) =>
    [
      i + 1,
      texto(p.name),
      texto(p.description),
      Number(p.price).toFixed(2), // número sin comillas: Excel lo reconoce como número
      Number(p.stock),
    ].join(','),
  )

  const contenido = [
    'sep=,', // le indica a Excel el separador, sin importar la configuración regional
    ENCABEZADOS.map(texto).join(','),
    ...filas,
  ].join('\r\n')

  // El BOM (\uFEFF) hace que Excel lea bien las tildes
  descargar(
    new Blob(['\uFEFF' + contenido], { type: 'text/csv;charset=utf-8;' }),
    'productos.csv',
  )
}

// ---------------------------------------------------------------------
// Excel (.xlsx) con una tabla real: encabezado con estilo, filtros y filas alternadas
// ---------------------------------------------------------------------
export const exportXlsx = async (productos) => {
  const libro = new ExcelJS.Workbook()
  const hoja = libro.addWorksheet('Productos', { views: [{ state: 'frozen', ySplit: 1 }] })

  hoja.addTable({
    name: 'TablaProductos',
    ref: 'A1',
    headerRow: true,
    style: { theme: 'TableStyleMedium2', showRowStripes: true },
    columns: ENCABEZADOS.map((nombre) => ({ name: nombre, filterButton: true })),
    rows: productos.map((p, i) => [
      i + 1,
      p.name,
      p.description ?? '',
      Number(p.price),
      Number(p.stock),
    ]),
  })

  const anchos = [6, 28, 42, 14, 10]
  anchos.forEach((ancho, i) => {
    hoja.getColumn(i + 1).width = ancho
  })
  hoja.getColumn(4).numFmt = '"$"#,##0.00'
  hoja.getColumn(1).alignment = { horizontal: 'center' }
  hoja.getColumn(5).alignment = { horizontal: 'center' }

  // Estilo explícito (además del de la tabla) para que también se vea bien en LibreOffice y Google Sheets
  const borde = { style: 'thin', color: { argb: 'FFCBD5E1' } }
  hoja.eachRow((fila, numero) => {
    fila.eachCell((celda) => {
      celda.border = { top: borde, left: borde, bottom: borde, right: borde }
      if (numero === 1) {
        celda.font = { bold: true, color: { argb: 'FFFFFFFF' } }
        celda.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF10B981' } }
        celda.alignment = { horizontal: 'center', vertical: 'middle' }
      }
    })
  })
  hoja.getRow(1).height = 22

  // Al imprimir, todo el ancho cabe en una página
  hoja.pageSetup = { orientation: 'landscape', fitToPage: true, fitToWidth: 1, fitToHeight: 0 }

  const buffer = await libro.xlsx.writeBuffer()
  descargar(
    new Blob([buffer], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    }),
    'productos.xlsx',
  )
}

// ---------------------------------------------------------------------
// PDF
// ---------------------------------------------------------------------
export const exportPdf = (productos) => {
  const doc = new jsPDF()
  doc.setFontSize(14)
  doc.text('Listado de productos', 14, 16)
  autoTable(doc, {
    startY: 22,
    head: [ENCABEZADOS],
    body: productos.map((p, i) => [
      i + 1,
      p.name,
      p.description ?? '',
      `$${Number(p.price).toFixed(2)}`,
      p.stock,
    ]),
  })
  doc.save('productos.pdf')
}
