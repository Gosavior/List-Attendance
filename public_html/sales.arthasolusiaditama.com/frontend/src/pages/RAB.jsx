import React, { useState, useEffect, useMemo } from 'react'
import { FiSearch, FiExternalLink, FiFileText, FiDownload } from 'react-icons/fi'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'

const API_BASE = import.meta.env.VITE_API_BASE || '/api'

const statusBadge = {
  NEAREST: 'bg-sky-100 text-sky-700 border border-sky-200',
  ONGOING: 'bg-purple-100 text-purple-700 border border-purple-200',
  DONE: 'bg-green-100 text-green-700 border border-green-200',
  LOST: 'bg-red-100 text-red-700 border border-red-200',
}

const statusLabel = {
  ONGOING: 'Ongoing',
  NEAREST: 'Nearest',
  DONE: 'Done',
}

const statusTabs = ['ALL', 'ONGOING', 'NEAREST', 'DONE']

const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  const date = new Date(dateStr)
  return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

const timeAgo = (dateStr) => {
  if (!dateStr) return '-'
  const now = new Date()
  const d = new Date(dateStr)
  const diffMs = now - d
  const mins = Math.floor(diffMs / 60000)
  if (mins < 1) return 'Baru saja'
  if (mins < 60) return `${mins} menit lalu`
  const hours = Math.floor(mins / 60)
  if (hours < 24) return `${hours} jam lalu`
  const days = Math.floor(hours / 24)
  if (days < 7) return `${days} hari lalu`
  const weeks = Math.floor(days / 7)
  if (weeks < 4) return `${weeks} minggu lalu`
  return formatDate(dateStr)
}

const formatRupiah = (val) => {
  if (!val && val !== 0) return '-'
  return 'Rp ' + Number(val).toLocaleString('id-ID')
}

const RAB = () => {
  const { authFetch, isAdmin } = useAuth()
  const [rabList, setRabList] = useState([])
  const [loading, setLoading] = useState(true)
  const [query, setQuery] = useState('')
  const [activeTab, setActiveTab] = useState('ALL')
  const [salesFilter, setSalesFilter] = useState('ALL')

  useEffect(() => {
    const fetchRABs = async () => {
      setLoading(true)
      try {
        const res = await authFetch(`${API_BASE}/rab`)
        const data = await res.json()
        if (data.success) setRabList(data.data)
      } catch (err) {
        console.error('Fetch RAB error:', err)
      } finally {
        setLoading(false)
      }
    }
    fetchRABs()
  }, [authFetch])

  
  const salesNames = useMemo(() => {
    if (!isAdmin) return []
    return [...new Set(rabList.map(r => r.sales_name).filter(Boolean))].sort()
  }, [rabList, isAdmin])

  
  const counts = useMemo(() => {
    const c = { ONGOING: 0, NEAREST: 0, DONE: 0 }
    rabList.forEach(r => { if (c[r.project_status] !== undefined) c[r.project_status]++ })
    c.total = rabList.length
    return c
  }, [rabList])

  
  const filtered = useMemo(() => {
    return rabList
      .filter(r => activeTab === 'ALL' || r.project_status === activeTab)
      .filter(r => salesFilter === 'ALL' || r.sales_name === salesFilter)
      .filter(r => {
        const target = `${r.project_name || ''} ${r.customer_name || ''} ${r.sales_name || ''} ${r.rab_number || ''}`.toLowerCase()
        return target.includes(query.toLowerCase())
      })
      .sort((a, b) => new Date(b.updated_at) - new Date(a.updated_at))
  }, [rabList, activeTab, salesFilter, query])

  return (
    <div className="w-full">
      { }
      <div className="bg-white rounded-lg shadow p-3 sm:p-4 mb-4">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex flex-wrap gap-2 items-center">
            {isAdmin && (
              <select
                value={salesFilter}
                onChange={e => setSalesFilter(e.target.value)}
                className="bg-white border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-stone-400 focus:border-stone-400"
              >
                <option value="ALL">Semua Sales</option>
                {salesNames.map(name => (
                  <option key={name} value={name}>{name}</option>
                ))}
              </select>
            )}

            <div className="flex flex-wrap gap-1.5">
              {statusTabs.map(s => (
                <button
                  key={s}
                  onClick={() => setActiveTab(s)}
                  className={`px-2.5 py-1 text-xs rounded border transition font-medium ${
                    activeTab === s
                      ? 'bg-stone-700 border-stone-600 text-white'
                      : 'bg-white border-gray-300 text-gray-600 hover:bg-gray-100'
                  }`}
                >
                  {s === 'ALL' ? `Semua (${counts.total})` : `${statusLabel[s]} (${counts[s]})`}
                </button>
              ))}
            </div>
          </div>

          <div className="relative w-full sm:w-64">
            <FiSearch className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={14} />
            <input
              value={query}
              onChange={e => setQuery(e.target.value)}
              placeholder="Cari RAB, project, customer..."
              className="w-full bg-white border border-gray-300 rounded-lg pl-9 pr-3 py-1.5 text-sm text-gray-700 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-stone-400 focus:border-stone-400"
            />
          </div>
        </div>
      </div>

      { }
      <div className="bg-white rounded-lg shadow overflow-hidden">
        {loading ? (
          <div className="flex items-center justify-center py-20 text-gray-400">
            <div className="animate-spin w-6 h-6 border-2 border-stone-300 border-t-stone-600 rounded-full mr-3" />
            Memuat data RAB...
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm min-w-[700px]">
              <thead>
                <tr className="bg-stone-700 text-left text-white text-xs uppercase tracking-wider">
                  <th className="py-3 px-4 font-semibold">Project</th>
                  {isAdmin && <th className="py-3 px-4 font-semibold">Sales</th>}
                  <th className="py-3 px-4 font-semibold">No. RAB</th>
                  <th className="py-3 px-4 font-semibold">Total</th>
                  <th className="py-3 px-4 font-semibold">Status</th>
                  <th className="py-3 px-4 font-semibold">Update</th>
                  <th className="py-3 px-4 font-semibold text-center">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {filtered.length === 0 ? (
                  <tr>
                    <td colSpan={isAdmin ? 7 : 6} className="py-10 text-center text-gray-400">
                      <FiFileText size={28} className="mx-auto mb-2 opacity-40" />
                      Tidak ada RAB ditemukan.
                    </td>
                  </tr>
                ) : filtered.map(rab => (
                  <RABRow key={rab.id} rab={rab} authFetch={authFetch} isAdmin={isAdmin} />
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  )
}

const RABRow = ({ rab, authFetch, isAdmin }) => {
  const toast = useToast()
  const [downloading, setDownloading] = useState(false)

  const handleDownload = async () => {
    setDownloading(true)
    try {
      
      const rabRes = await authFetch(`${API_BASE}/rab/${rab.id}`)
      const rabData = await rabRes.json()
      if (!rabData.success) throw new Error('Gagal mengambil detail RAB')

      
      const projRes = await authFetch(`${API_BASE}/projects/${rab.project_id}`)
      const projData = await projRes.json()

      const rabDetail = rabData.data
      const project = projData.success ? projData.data : {}

      const overflowItems = (rabDetail.sectionB || []).filter(r => {
        const needed = parseFloat(r.qtyNeeded) || 0
        const available = parseFloat(r.qtyAvailable) || 0
        return needed > available && (r.item || '').trim()
      }).map(r => {
        const toBuy = Math.max(0, (parseFloat(r.qtyNeeded) || 0) - (parseFloat(r.qtyAvailable) || 0))
        return { item: r.item, qty: toBuy, unit: r.unit, price: parseFloat(r.price) || 0, store: r.store || '', receipt: r.receipt || '' }
      })

      const pembelianItems = (rabDetail.sectionA || []).filter(r => (r.item || '').trim())
        .map(r => ({ item: r.item, qty: parseFloat(r.qty) || 0, unit: r.unit, price: parseFloat(r.price) || 0, store: r.store || '', receipt: r.receipt || '' }))

      const allPurchased = [...pembelianItems, ...overflowItems]

      const storeGroups = {}
      allPurchased.forEach(item => {
        const storeName = (item.store || '').trim() || 'Lainnya'
        if (!storeGroups[storeName]) storeGroups[storeName] = []
        storeGroups[storeName].push(item)
      })
      const storeNames = Object.keys(storeGroups).sort((a, b) => a === 'Lainnya' ? 1 : b === 'Lainnya' ? -1 : a.localeCompare(b))

      const gudangItems = (rabDetail.sectionB || []).filter(r => (r.item || '').trim())
        .map(r => {
          const needed = parseFloat(r.qtyNeeded) || 0
          const available = parseFloat(r.qtyAvailable) || 0
          const fromWarehouse = Math.min(needed, available)
          return { item: r.item, qty: fromWarehouse, unit: r.unit, price: parseFloat(r.price) || 0 }
        }).filter(r => r.qty > 0)

      const spkItems = (rabDetail.sectionC || []).filter(r => (r.item || '').trim())
        .map(r => ({ item: r.item, qty: parseFloat(r.qty) || 0, unit: r.unit, price: parseFloat(r.price) || 0 }))

      const [{ default: ExcelJS }, { saveAs }] = await Promise.all([
        import('exceljs'),
        import('file-saver'),
      ])
      const workbook = new ExcelJS.Workbook()
      const ws = workbook.addWorksheet('RAB')

      ws.columns = [
        { width: 6 },
        { width: 35 },
        { width: 10 },
        { width: 18 },
        { width: 18 },
        { width: 20 },
        { width: 18 },
        { width: 18 },
      ]

      const border = { top: { style: 'thin' }, bottom: { style: 'thin' }, left: { style: 'thin' }, right: { style: 'thin' } }
      const currencyFmt = '#,##0'

      const addSectionHeader = (text, fillColor) => {
        const row = ws.addRow([text])
        ws.mergeCells(row.number, 1, row.number, 8)
        row.eachCell(c => {
          c.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: fillColor } }
          c.font = { bold: true, size: 10 }
          c.border = border
        })
        return row
      }

      const fetchImageBase64 = async (filename) => {
        try {
          const url = `${API_BASE.replace('/api', '')}/uploads/receipts/${filename}`
          const response = await fetch(url)
          if (!response.ok) return null
          const blob = await response.blob()
          return new Promise((resolve) => {
            const reader = new FileReader()
            reader.onloadend = () => resolve(reader.result)
            reader.onerror = () => resolve(null)
            reader.readAsArrayBuffer(blob)
          })
        } catch {
          return null
        }
      }

      const getImageExt = (filename) => {
        const ext = (filename || '').split('.').pop().toLowerCase()
        if (ext === 'png') return 'png'
        if (ext === 'jpg' || ext === 'jpeg') return 'jpeg'
        return null
      }

      const imageQueue = []

      const addItemRows = (items, showStore = false, showReceipt = false) => {
        let subtotal = 0
        items.forEach((item, idx) => {
          const total = item.qty * item.price
          subtotal += total
          const row = ws.addRow([idx + 1, item.item || '', item.qty || '', item.price || '', total || '', showStore ? (item.store || '') : '', '', ''])
          row.eachCell(c => { c.border = border; c.font = { size: 10 } })
          row.getCell(4).numFmt = currencyFmt
          row.getCell(5).numFmt = currencyFmt
          row.getCell(1).alignment = { horizontal: 'center' }
          row.getCell(3).alignment = { horizontal: 'center' }
          row.getCell(4).alignment = { horizontal: 'right' }
          row.getCell(5).alignment = { horizontal: 'right' }
          if (showReceipt && item.receipt && getImageExt(item.receipt)) {
            imageQueue.push({ rowNum: row.number, filename: item.receipt })
          }
        })
        return subtotal
      }

      const addSubtotalRow = (subtotal) => {
        const row = ws.addRow(['', 'Subtotal', '', '', subtotal, '', '', ''])
        ws.mergeCells(row.number, 1, row.number, 4)
        row.eachCell(c => { c.border = border; c.font = { bold: true, size: 10 } })
        row.getCell(1).alignment = { horizontal: 'center' }
        row.getCell(5).numFmt = currencyFmt
        row.getCell(5).alignment = { horizontal: 'right' }
        return row
      }

      const headerFill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF92D050' } }
      const r1 = ws.addRow(['Projek', ':', rab.project_name || ''])
      ws.mergeCells(r1.number, 3, r1.number, 8)
      r1.eachCell(c => { c.fill = headerFill; c.font = { bold: true, size: 10 }; c.border = border })

      const r2 = ws.addRow(['Date', ':', formatDate(rabDetail.created_at)])
      ws.mergeCells(r2.number, 3, r2.number, 8)
      r2.eachCell(c => { c.fill = headerFill; c.font = { bold: true, size: 10 }; c.border = border })

      const r3 = ws.addRow(['AO', ':', project.ao_number || ''])
      ws.mergeCells(r3.number, 3, r3.number, 8)
      r3.eachCell(c => { c.font = { bold: true, size: 10 }; c.border = border })

      ws.addRow([])

      const colHeader = ws.addRow(['No', 'Item', 'Qty', 'Harga Satuan', 'Total', 'Toko', 'NO INV/PO', 'Struk'])
      colHeader.eachCell(c => {
        c.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFFCC00' } }
        c.font = { bold: true, size: 10 }
        c.border = border
        c.alignment = { horizontal: 'center' }
      })

      addSectionHeader('A. PO / Pembelian', 'FFFF9900')

      const storeColors = ['FF92D050', 'FFFFD966', 'FFB4C6E7', 'FFFFC7CE', 'FFD9E2F3', 'FFE2EFDA', 'FFFFF2CC']
      let sectionAPurchaseTotal = 0
      storeNames.forEach((storeName, sIdx) => {
        const items = storeGroups[storeName]
        const color = storeColors[sIdx % storeColors.length]
        addSectionHeader(storeName, color)
        const storeSubtotal = addItemRows(items, false, true)
        addSubtotalRow(storeSubtotal)
        sectionAPurchaseTotal += storeSubtotal
      })

      const sectionATotalRow = ws.addRow(['', 'Total Pembelian', '', '', sectionAPurchaseTotal, '', '', ''])
      ws.mergeCells(sectionATotalRow.number, 1, sectionATotalRow.number, 4)
      sectionATotalRow.eachCell(c => {
        c.border = border
        c.font = { bold: true, size: 10 }
        c.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFB4C6E7' } }
      })
      sectionATotalRow.getCell(5).numFmt = currencyFmt
      sectionATotalRow.getCell(5).alignment = { horizontal: 'right' }

      addSectionHeader('B. Material Gudang', 'FFFFFF00')
      const gudangSubtotal = addItemRows(gudangItems)
      addSubtotalRow(gudangSubtotal)

      addSectionHeader('C. SPK', 'FFFFFF00')
      const spkSubtotal = addItemRows(spkItems)
      addSubtotalRow(spkSubtotal)

      const grandTotal = sectionAPurchaseTotal + gudangSubtotal + spkSubtotal
      const totalRow = ws.addRow(['', 'TOTAL', '', 'Rp', grandTotal, '', '', ''])
      ws.mergeCells(totalRow.number, 1, totalRow.number, 3)
      totalRow.eachCell(c => {
        c.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFFFF00' } }
        c.font = { bold: true, size: 11 }
        c.border = border
      })
      totalRow.getCell(5).numFmt = currencyFmt
      totalRow.getCell(5).alignment = { horizontal: 'right' }

      
      for (const img of imageQueue) {
        const ext = getImageExt(img.filename)
        if (!ext) continue
        const imgData = await fetchImageBase64(img.filename)
        if (!imgData) continue
        try {
          const imageId = workbook.addImage({ buffer: imgData, extension: ext })
          
          ws.getRow(img.rowNum).height = 60
          ws.addImage(imageId, {
            tl: { col: 7, row: img.rowNum - 1 },
            br: { col: 8, row: img.rowNum },
            editAs: 'oneCell',
          })
        } catch (imgErr) {
          console.warn('Failed to embed image:', img.filename, imgErr)
        }
      }

      
      const buffer = await workbook.xlsx.writeBuffer()
      const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' })
      const fileName = `RAB_${(rab.project_name || 'project').replace(/[^a-zA-Z0-9]/g, '_')}.xlsx`
      saveAs(blob, fileName)
    } catch (err) {
      console.error('Download RAB error:', err)
      toast.error('Gagal download RAB: ' + (err.message || 'Unknown error'))
    } finally {
      setDownloading(false)
    }
  }

  return (
    <tr className="hover:bg-gray-50 transition">
      <td className="py-3 px-4">
        <p className="font-semibold text-gray-800">{rab.project_name}</p>
        <p className="text-xs text-gray-400">{rab.customer_name}</p>
      </td>
      {isAdmin && <td className="py-3 px-4 text-gray-600">{rab.sales_name}</td>}
      <td className="py-3 px-4">
        {rab.rab_number ? (
          <span className="text-xs bg-stone-100 text-stone-600 border border-stone-200 px-1.5 py-0.5 rounded font-medium">{rab.rab_number}</span>
        ) : (
          <span className="text-xs text-gray-400">-</span>
        )}
        {rab.rab_type === 'bayangan' && (
          <span className="inline-block ml-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-100 text-amber-600 border border-amber-200">Bayangan</span>
        )}
      </td>
      <td className="py-3 px-4 text-gray-800 font-medium">{formatRupiah(rab.grand_total)}</td>
      <td className="py-3 px-4">
        <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${statusBadge[rab.project_status] || 'bg-gray-100 text-gray-600'}`}>
          {statusLabel[rab.project_status] || rab.project_status}
        </span>
        {rab.status === 'draft' && (
          <span className="inline-block ml-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-100 text-amber-700 border border-amber-200">Draft</span>
        )}
      </td>
      <td className="py-3 px-4 text-xs text-gray-400">{timeAgo(rab.updated_at)}</td>
      <td className="py-3 px-4 text-center">
        <div className="flex items-center justify-center gap-1.5">
          <button
            onClick={handleDownload}
            disabled={downloading}
            className="flex items-center gap-1 text-xs bg-green-600 hover:bg-green-500 text-white px-2.5 py-1.5 rounded transition font-medium disabled:opacity-50"
            title="Download Excel"
          >
            <FiDownload size={12} />
            {downloading ? '...' : 'Excel'}
          </button>
          <button
            onClick={() => window.open(`/create-rab?rab=${rab.id}&project=${rab.project_id}${rab.rab_type ? '&type=' + rab.rab_type : ''}`, '_blank')}
            className="flex items-center gap-1 text-xs bg-stone-600 hover:bg-stone-500 text-white px-2.5 py-1.5 rounded transition font-medium"
          >
            <FiExternalLink size={12} />
            Lihat
          </button>
        </div>
      </td>
    </tr>
  )
}

export default RAB
