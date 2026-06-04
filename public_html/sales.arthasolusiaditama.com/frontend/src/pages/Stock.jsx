import React, { useState, useEffect, useCallback, useRef } from 'react'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import { useSearchParams } from 'react-router-dom'
import { FiPackage, FiPlus, FiUpload, FiSearch, FiEdit2, FiTrash2, FiAlertTriangle, FiChevronLeft, FiChevronRight, FiX, FiDownload, FiRefreshCw, FiTrendingDown, FiTrendingUp, FiClipboard, FiShoppingCart, FiSliders } from 'react-icons/fi'
import * as ExcelJS from 'exceljs'
import { saveAs } from 'file-saver'

const API_BASE = import.meta.env.VITE_API_BASE || '/api'



const formatCurrency = (val) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0)
const formatNumber = (val) => new Intl.NumberFormat('id-ID').format(val || 0)

const mappingFields = [
  { key: 'material_name', label: 'Nama Material', required: true },
  { key: 'material_code', label: 'Kode Produk (SKU)' },
  { key: 'unit', label: 'Satuan' },
  { key: 'stock_qty', label: 'Jumlah Stok (Tercatat)' },
  { key: 'actual_qty', label: 'Jumlah Sebenarnya' },
  { key: 'category', label: 'Kategori' },
  { key: 'description', label: 'Deskripsi' },
  { key: 'avg_price', label: 'Harga Rata-rata' },
  { key: 'min_stock', label: 'Stok Minimum' },
  { key: 'supplier', label: 'Supplier' },
  { key: 'location', label: 'Lokasi Gudang' }
]



export default function Stock() {
  const { user, authFetch } = useAuth()
  const toast = useToast()
  const showToast = (message, type = 'info') => toast[type] ? toast[type](message) : toast.info(message)
  const isAdmin = user?.role === 'administrator' || user?.role === 'direktur'
  const [searchParams, setSearchParams] = useSearchParams()
  const [highlightId, setHighlightId] = useState(null)
  const highlightRef = useRef(null)

  const [items, setItems] = useState([])
  const [categories, setCategories] = useState([])
  const [summary, setSummary] = useState({})
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [category, setCategory] = useState('')
  const [lowStock, setLowStock] = useState(false)
  const [page, setPage] = useState(1)
  const [pagination, setPagination] = useState({})

  
  const [showForm, setShowForm] = useState(false)
  const [showImport, setShowImport] = useState(false)
  const [showDetail, setShowDetail] = useState(null)
  const [editItem, setEditItem] = useState(null)
  const [showMovements, setShowMovements] = useState(false)
  const [purchaseItem, setPurchaseItem] = useState(null)
  const [adjustItem, setAdjustItem] = useState(null)

  
  const [form, setForm] = useState({
    material_code: '', material_name: '', category: '', description: '',
    unit: 'pcs', stock_qty: 0, avg_price: 0, min_stock: 0, supplier: '', location: ''
  })

  
  const [importData, setImportData] = useState([])
  const [importMapping, setImportMapping] = useState({})
  const [importHeaders, setImportHeaders] = useState([])
  const [importStep, setImportStep] = useState(1)
  const [importing, setImporting] = useState(false)

  
  const [purchaseForm, setPurchaseForm] = useState({ quantity: '', price_per_unit: '', notes: '' })

  
  const [adjustForm, setAdjustForm] = useState({ new_qty: '', notes: '' })

  
  const [movements, setMovements] = useState([])

  
  
  
  const fetchStock = useCallback(async () => {
    setLoading(true)
    try {
      const params = new URLSearchParams({ page, limit: 50 })
      if (search) params.set('search', search)
      if (category) params.set('category', category)
      if (lowStock) params.set('low_stock', '1')
      const res = await authFetch(`${API_BASE}/stock?${params}`)
      const data = await res.json()
      if (data.success) {
        setItems(data.data)
        setCategories(data.categories || [])
        setSummary(data.summary || {})
        setPagination(data.pagination || {})
      }
    } catch (e) { showToast('Gagal memuat data stok', 'error') }
    finally { setLoading(false) }
  }, [authFetch, search, category, lowStock, page])

  useEffect(() => { fetchStock() }, [fetchStock])

  
  useEffect(() => {
    const urlSearch = searchParams.get('search')
    const urlHighlight = searchParams.get('highlight')
    if (urlSearch && urlSearch !== search) setSearch(urlSearch)
    if (urlHighlight) setHighlightId(Number(urlHighlight))
    if (urlSearch || urlHighlight) {
      setSearchParams({}, { replace: true })
    }
  }, []) 

  
  useEffect(() => {
    if (highlightId && items.length > 0 && highlightRef.current) {
      highlightRef.current.scrollIntoView({ behavior: 'smooth', block: 'center' })
      const timer = setTimeout(() => setHighlightId(null), 3000)
      return () => clearTimeout(timer)
    }
  }, [highlightId, items])

  
  
  
  const resetForm = () => setForm({
    material_code: '', material_name: '', category: '', description: '',
    unit: 'pcs', stock_qty: 0, avg_price: 0, min_stock: 0, supplier: '', location: ''
  })

  const handleSave = async () => {
    if (!form.material_name.trim()) return showToast('Nama material wajib diisi', 'error')
    try {
      const url = editItem ? `${API_BASE}/stock/${editItem.id}` : `${API_BASE}/stock`
      const method = editItem ? 'PUT' : 'POST'
      const body = editItem
        ? { material_code: form.material_code, material_name: form.material_name, category: form.category, description: form.description, unit: form.unit, min_stock: form.min_stock, supplier: form.supplier, location: form.location }
        : form
      const res = await authFetch(url, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
      const data = await res.json()
      if (data.success) { showToast(data.message, 'success'); setShowForm(false); setEditItem(null); resetForm(); fetchStock() }
      else showToast(data.message, 'error')
    } catch (e) { showToast('Gagal menyimpan data', 'error') }
  }

  const handleDelete = async (id) => {
    if (!confirm('Yakin ingin menghapus material ini?')) return
    try {
      const res = await authFetch(`${API_BASE}/stock/${id}`, { method: 'DELETE' })
      const data = await res.json()
      if (data.success) { showToast(data.message, 'success'); fetchStock() }
      else showToast(data.message, 'error')
    } catch (e) { showToast('Gagal menghapus', 'error') }
  }

  const handleEdit = (item) => {
    setEditItem(item)
    setForm({
      material_code: item.material_code || '', material_name: item.material_name || '',
      category: item.category || '', description: item.description || '',
      unit: item.unit || 'pcs', stock_qty: item.stock_qty || 0,
      avg_price: item.avg_price || 0, min_stock: item.min_stock || 0,
      supplier: item.supplier || '', location: item.location || ''
    })
    setShowForm(true)
  }

  
  
  
  const handlePurchase = async () => {
    const qty = parseFloat(purchaseForm.quantity)
    const price = parseFloat(purchaseForm.price_per_unit)
    if (!qty || qty <= 0) return showToast('Jumlah harus lebih dari 0', 'error')
    if (!price || price < 0) return showToast('Harga per unit wajib diisi', 'error')
    try {
      const res = await authFetch(`${API_BASE}/stock/${purchaseItem.id}/purchase`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(purchaseForm)
      })
      const data = await res.json()
      if (data.success) { showToast(data.message, 'success'); setPurchaseItem(null); setPurchaseForm({ quantity: '', price_per_unit: '', notes: '' }); fetchStock() }
      else showToast(data.message, 'error')
    } catch (e) { showToast('Gagal mencatat pembelian', 'error') }
  }

  
  
  
  const handleAdjust = async () => {
    const newQty = parseFloat(adjustForm.new_qty)
    if (isNaN(newQty) || newQty < 0) return showToast('Jumlah stok tidak valid', 'error')
    try {
      const res = await authFetch(`${API_BASE}/stock/${adjustItem.id}/adjust`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(adjustForm)
      })
      const data = await res.json()
      if (data.success) { showToast(data.message, 'success'); setAdjustItem(null); setAdjustForm({ new_qty: '', notes: '' }); fetchStock() }
      else showToast(data.message, 'error')
    } catch (e) { showToast('Gagal menyesuaikan stok', 'error') }
  }

  
  
  
  const handleFileUpload = async (e) => {
    const file = e.target.files[0]
    if (!file) return
    try {
      const wb = new ExcelJS.Workbook()
      await wb.xlsx.load(await file.arrayBuffer())
      const ws = wb.worksheets[0]
      if (!ws || ws.rowCount < 2) return showToast('File Excel kosong', 'error')

      
      const knownKeywords = ['nama', 'name', 'material', 'produk', 'item', 'barang', 'kode', 'code', 'sku', 'satuan', 'unit', 'kategori', 'category', 'stok', 'stock', 'harga', 'price', 'qty', 'jumlah']
      let headerRowNum = 1
      ws.eachRow((row, rowNumber) => {
        if (headerRowNum > 1) return 
        let matchCount = 0
        row.eachCell(cell => {
          const val = (cell.value || '').toString().toLowerCase()
          if (knownKeywords.some(k => val.includes(k))) matchCount++
        })
        if (matchCount >= 2) headerRowNum = rowNumber
      })

      const headers = []
      ws.getRow(headerRowNum).eachCell((cell, colNumber) => {
        headers.push({ col: colNumber, name: (cell.value || '').toString().trim() })
      })
      setImportHeaders(headers)

      
      const autoMap = {}
      const keywords = {
        material_name: ['nama', 'name', 'material', 'item', 'barang', 'produk'],
        material_code: ['kode', 'code', 'sku', 'id'],
        unit: ['satuan', 'unit', 'uom'],
        stock_qty: ['tercatat'],
        actual_qty: ['sebenarnya', 'aktual', 'actual', 'fisik'],
        category: ['kategori', 'category', 'jenis', 'tipe', 'type', 'group'],
        description: ['deskripsi', 'description', 'keterangan', 'desc'],
        avg_price: ['harga', 'price', 'rata'],
        min_stock: ['minimum', 'min', 'reorder'],
        supplier: ['supplier', 'vendor', 'pemasok', 'toko'],
        location: ['lokasi', 'location', 'gudang', 'warehouse']
      }
      for (const h of headers) {
        const lower = h.name.toLowerCase()
        for (const [field, kws] of Object.entries(keywords)) {
          if (kws.some(k => lower.includes(k)) && !autoMap[field]) { autoMap[field] = h.col; break }
        }
      }
      setImportMapping(autoMap)

      const rows = []
      ws.eachRow((row, rowNumber) => {
        if (rowNumber <= headerRowNum) return
        const rowData = {}
        row.eachCell((cell, colNumber) => {
          rowData[colNumber] = cell.value !== null && cell.value !== undefined
            ? (typeof cell.value === 'object' && cell.value.result !== undefined ? cell.value.result : cell.value) : ''
        })
        if (Object.values(rowData).some(v => v !== '')) rows.push(rowData)
      })

      setImportData(rows)
      setImportStep(2)
      showToast(`${rows.length} baris data ditemukan (header di baris ${headerRowNum})`, 'success')
    } catch (err) { showToast('Gagal membaca file Excel', 'error') }
    e.target.value = ''
  }

  const handleImportSubmit = async () => {
    if (!importMapping.material_name) return showToast('Mapping "Nama Material" wajib', 'error')
    setImporting(true)
    try {
      const mappedItems = importData.map(row => {
        const item = {}
        for (const [field, col] of Object.entries(importMapping)) { if (col) item[field] = row[col] || '' }
        return item
      }).filter(i => i.material_name && i.material_name.toString().trim())

      if (mappedItems.length === 0) { showToast('Tidak ada data valid', 'error'); setImporting(false); return }

      const res = await authFetch(`${API_BASE}/stock/import`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ items: mappedItems })
      })
      const data = await res.json()
      if (data.success) {
        showToast(data.message, 'success')
        setShowImport(false); setImportData([]); setImportHeaders([]); setImportMapping({}); setImportStep(1)
        fetchStock()
      } else showToast(data.message, 'error')
    } catch (e) { showToast('Gagal import data', 'error') }
    finally { setImporting(false) }
  }

  
  
  
  const handleExportTemplate = async () => {
    const wb = new ExcelJS.Workbook()
    const ws = wb.addWorksheet('Template Stock')
    ws.columns = [
      { header: 'Kode Produk (SKU)', key: 'material_code', width: 18 },
      { header: 'Nama Material', key: 'material_name', width: 35 },
      { header: 'Kategori', key: 'category', width: 20 },
      { header: 'Deskripsi', key: 'description', width: 30 },
      { header: 'Satuan', key: 'unit', width: 12 },
      { header: 'Jumlah Stok', key: 'stock_qty', width: 15 },
      { header: 'Harga Rata-rata', key: 'avg_price', width: 18 },
      { header: 'Stok Minimum', key: 'min_stock', width: 15 },
      { header: 'Supplier', key: 'supplier', width: 25 },
      { header: 'Lokasi Gudang', key: 'location', width: 18 }
    ]
    ws.getRow(1).eachCell(cell => {
      cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF1E293B' } }
      cell.font = { bold: true, color: { argb: 'FFFFFFFF' } }
      cell.alignment = { horizontal: 'center' }
    })
    ws.addRow({ material_code: 'KBL-001', material_name: 'Kabel NYM 3x2.5mm', category: 'Kabel', description: 'Kabel tembaga NYM', unit: 'meter', stock_qty: 500, avg_price: 15000, min_stock: 100, supplier: 'PT Kabel Nusantara', location: 'Gudang A' })
    const buf = await wb.xlsx.writeBuffer()
    saveAs(new Blob([buf]), 'template_stock_material.xlsx')
    showToast('Template berhasil diunduh', 'success')
  }

  const handleExportData = async () => {
    const wb = new ExcelJS.Workbook()
    const ws = wb.addWorksheet('Data Stock')
    ws.columns = [
      { header: 'Kode', key: 'material_code', width: 15 },
      { header: 'Nama Material', key: 'material_name', width: 35 },
      { header: 'Kategori', key: 'category', width: 20 },
      { header: 'Satuan', key: 'unit', width: 12 },
      { header: 'Stok', key: 'stock_qty', width: 15 },
      { header: 'Harga Rata-rata', key: 'avg_price', width: 18 },
      { header: 'Harga Terakhir', key: 'last_price', width: 18 },
      { header: 'Total Nilai', key: 'total_value', width: 18 },
      { header: 'Stok Minimum', key: 'min_stock', width: 15 }
    ]
    ws.getRow(1).eachCell(cell => {
      cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF1E293B' } }
      cell.font = { bold: true, color: { argb: 'FFFFFFFF' } }
      cell.alignment = { horizontal: 'center' }
    })
    items.forEach(i => ws.addRow({
      material_code: i.material_code || '-', material_name: i.material_name,
      category: i.category || '-', unit: i.unit, stock_qty: i.stock_qty,
      avg_price: i.avg_price, last_price: i.last_price, total_value: i.total_value || 0, min_stock: i.min_stock
    }))
    const buf = await wb.xlsx.writeBuffer()
    saveAs(new Blob([buf]), `stock_material_${new Date().toISOString().slice(0, 10)}.xlsx`)
    showToast('Data berhasil diexport', 'success')
  }

  
  const fetchMovements = async () => {
    try {
      const res = await authFetch(`${API_BASE}/stock/movements/history?limit=50`)
      const data = await res.json()
      if (data.success) setMovements(data.data)
    } catch (e) {   }
  }

  
  
  
  return (
    <div className='space-y-4'>
      { }
      <div className='flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3'>
        <div>
          <h1 className='text-xl font-bold text-slate-800 flex items-center gap-2'><FiPackage /> Stock Material</h1>
          <p className='text-sm text-slate-500'>Kelola stok material gudang</p>
        </div>
        {isAdmin && (
          <div className='flex gap-2 flex-wrap'>
            <button onClick={() => { setShowImport(true); setImportStep(1) }} className='flex items-center gap-1.5 px-3 py-2 bg-emerald-600 text-white text-sm rounded-lg hover:bg-emerald-700 transition'>
              <FiUpload size={16} /> Import Excel
            </button>
            <button onClick={() => { resetForm(); setEditItem(null); setShowForm(true) }} className='flex items-center gap-1.5 px-3 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition'>
              <FiPlus size={16} /> Tambah Material
            </button>
          </div>
        )}
      </div>

      { }
      <div className='space-y-3'>
        <div className='grid grid-cols-2 md:grid-cols-4 gap-2 sm:gap-3'>
          <div className='bg-white rounded-xl p-3 sm:p-4 border border-slate-200'>
            <p className='text-[10px] sm:text-xs text-slate-500 mb-1'>Total Item</p>
            <p className='text-lg sm:text-2xl font-bold text-slate-800'>{formatNumber(summary.total_items)}</p>
          </div>
          <div className='bg-white rounded-xl p-3 sm:p-4 border border-slate-200'>
            <p className='text-[10px] sm:text-xs text-slate-500 mb-1'>Total Stok</p>
            <p className='text-lg sm:text-2xl font-bold text-blue-600'>{formatNumber(summary.total_stock)}</p>
          </div>
          <div className='bg-white rounded-xl p-3 sm:p-4 border border-slate-200'>
            <p className='text-[10px] sm:text-xs text-slate-500 mb-1'>Nilai Stok</p>
            <p className='text-sm sm:text-lg font-bold text-emerald-600 break-all'>{formatCurrency(summary.total_value)}</p>
          </div>
          <div className={`bg-white rounded-xl p-3 sm:p-4 border ${summary.low_stock_count > 0 ? 'border-red-300 bg-red-50' : 'border-slate-200'}`}>
            <p className='text-[10px] sm:text-xs text-slate-500 mb-1'>Stok Rendah</p>
            <p className={`text-lg sm:text-2xl font-bold ${summary.low_stock_count > 0 ? 'text-red-600' : 'text-slate-400'}`}>{formatNumber(summary.low_stock_count)}</p>
          </div>
        </div>
        <div className='grid grid-cols-2 gap-2 sm:gap-3'>
          <div className='bg-white rounded-xl p-3 sm:p-4 border border-emerald-200'>
            <div className='flex items-center gap-1.5 mb-1'>
              <span className='w-2 h-2 rounded-full bg-emerald-500'></span>
              <p className='text-[10px] sm:text-xs text-emerald-600 font-medium'>Masuk Bulan Ini</p>
            </div>
            <p className='text-sm sm:text-lg font-bold text-emerald-700 break-all'>{formatCurrency(summary.month_in_value)}</p>
            <p className='text-[10px] text-emerald-500 mt-0.5'>{formatNumber(summary.month_in_qty)} unit</p>
          </div>
          <div className='bg-white rounded-xl p-3 sm:p-4 border border-orange-200'>
            <div className='flex items-center gap-1.5 mb-1'>
              <span className='w-2 h-2 rounded-full bg-orange-500'></span>
              <p className='text-[10px] sm:text-xs text-orange-600 font-medium'>Keluar Bulan Ini</p>
            </div>
            <p className='text-sm sm:text-lg font-bold text-orange-700 break-all'>{formatCurrency(summary.month_out_value)}</p>
            <p className='text-[10px] text-orange-500 mt-0.5'>{formatNumber(summary.month_out_qty)} unit</p>
          </div>
        </div>
      </div>

      { }
      <div className='bg-white rounded-xl p-4 border border-slate-200'>
        <div className='flex flex-col sm:flex-row gap-3'>
          <div className='relative flex-1'>
            <FiSearch className='absolute left-3 top-1/2 -translate-y-1/2 text-slate-400' size={16} />
            <input type='text' placeholder='Cari nama, kode, atau deskripsi...' value={search} onChange={e => { setSearch(e.target.value); setPage(1) }}
              className='w-full pl-9 pr-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500' />
          </div>
          <select value={category} onChange={e => { setCategory(e.target.value); setPage(1) }} className='px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500'>
            <option value=''>Semua Kategori</option>
            {categories.map(c => <option key={c} value={c}>{c}</option>)}
          </select>
          <button onClick={() => { setLowStock(!lowStock); setPage(1) }}
            className={`flex items-center gap-1.5 px-3 py-2 text-sm rounded-lg border transition ${lowStock ? 'bg-red-50 border-red-300 text-red-700' : 'border-slate-300 text-slate-600 hover:bg-slate-50'}`}>
            <FiAlertTriangle size={14} /> Stok Rendah
          </button>
          <button onClick={fetchStock} className='p-2 text-slate-500 hover:text-slate-700 border border-slate-300 rounded-lg hover:bg-slate-50 transition'><FiRefreshCw size={16} /></button>
          <div className='flex gap-1'>
            <button onClick={handleExportTemplate} className='flex items-center gap-1 px-3 py-2 text-sm border border-slate-300 rounded-lg text-slate-600 hover:bg-slate-50' title='Template'><FiDownload size={14} /> Template</button>
            <button onClick={handleExportData} className='flex items-center gap-1 px-3 py-2 text-sm border border-slate-300 rounded-lg text-slate-600 hover:bg-slate-50' title='Export'><FiDownload size={14} /> Export</button>
            {isAdmin && <button onClick={() => { fetchMovements(); setShowMovements(true) }} className='flex items-center gap-1 px-3 py-2 text-sm border border-slate-300 rounded-lg text-slate-600 hover:bg-slate-50' title='Riwayat'><FiClipboard size={14} /> Riwayat</button>}
          </div>
        </div>
      </div>

      { }
      <div className='bg-white rounded-xl border border-slate-200 overflow-hidden'>
        <div className='overflow-x-auto'>
          <table className='w-full text-sm'>
            <thead>
              <tr className='bg-slate-50 text-slate-600 text-left'>
                <th className='px-4 py-3 font-semibold'>Kode</th>
                <th className='px-4 py-3 font-semibold'>Nama Material</th>
                <th className='px-4 py-3 font-semibold'>Kategori</th>
                <th className='px-4 py-3 font-semibold text-center'>Satuan</th>
                <th className='px-4 py-3 font-semibold text-right'>Tercatat</th>
                <th className='px-4 py-3 font-semibold text-right'>Sebenarnya</th>
                <th className='px-4 py-3 font-semibold text-right'>Selisih</th>
                <th className='px-4 py-3 font-semibold text-right'>Harga Avg</th>
                <th className='px-4 py-3 font-semibold text-center'>Status</th>
                {isAdmin && <th className='px-4 py-3 font-semibold text-center'>Aksi</th>}
              </tr>
            </thead>
            <tbody className='divide-y divide-slate-100'>
              {loading ? (
                <tr><td colSpan={isAdmin ? 10 : 9} className='px-4 py-12 text-center text-slate-400'>
                  <div className='w-6 h-6 border-2 border-slate-300 border-t-slate-600 rounded-full animate-spin mx-auto mb-2' />Memuat data...</td></tr>
              ) : items.length === 0 ? (
                <tr><td colSpan={isAdmin ? 10 : 9} className='px-4 py-12 text-center text-slate-400'>
                  <FiPackage className='mx-auto mb-2' size={32} />Belum ada data stok material</td></tr>
              ) : items.map(item => {
                const diff = item.actual_qty !== null && item.actual_qty !== undefined ? parseFloat(item.actual_qty) - parseFloat(item.stock_qty) : null
                const isHighlighted = highlightId === item.id
                return (
                <tr key={item.id} ref={isHighlighted ? highlightRef : null} className={`hover:bg-slate-50 transition cursor-pointer ${item.has_discrepancy ? 'bg-red-50/40' : ''} ${isHighlighted ? 'ring-2 ring-blue-400 bg-blue-50/60 animate-pulse' : ''}`} onClick={() => setShowDetail(item)}>
                  <td className='px-4 py-3 text-slate-500 font-mono text-xs'>{item.material_code || '-'}</td>
                  <td className='px-4 py-3 font-medium text-slate-800'>
                    {item.material_name}
                    {item.description && <p className='text-xs text-slate-400 mt-0.5 truncate max-w-xs'>{item.description}</p>}
                  </td>
                  <td className='px-4 py-3'>{item.category ? <span className='px-2 py-0.5 bg-slate-100 text-slate-600 rounded text-xs'>{item.category}</span> : '-'}</td>
                  <td className='px-4 py-3 text-center text-slate-500'>{item.unit}</td>
                  <td className={`px-4 py-3 text-right font-semibold ${item.is_low_stock ? 'text-red-600' : 'text-slate-800'}`}>{formatNumber(item.stock_qty)}</td>
                  <td className='px-4 py-3 text-right font-semibold'>
                    {item.actual_qty !== null && item.actual_qty !== undefined
                      ? <span className={item.has_discrepancy ? 'text-red-600' : 'text-emerald-600'}>{formatNumber(item.actual_qty)}</span>
                      : <span className='text-slate-300'>-</span>}
                  </td>
                  <td className='px-4 py-3 text-right'>
                    {diff !== null
                      ? <span className={`font-semibold ${diff === 0 ? 'text-emerald-600' : diff < 0 ? 'text-red-600' : 'text-amber-600'}`}>{diff > 0 ? '+' : ''}{formatNumber(diff)}</span>
                      : <span className='text-slate-300'>-</span>}
                  </td>
                  <td className='px-4 py-3 text-right text-slate-600'>{formatCurrency(item.avg_price)}</td>
                  <td className='px-4 py-3 text-center'>
                    {item.has_discrepancy
                      ? <span className='inline-flex items-center gap-1 px-2 py-0.5 bg-red-50 text-red-600 rounded-full text-xs font-medium'><FiAlertTriangle size={10} /> Selisih</span>
                      : item.is_low_stock
                      ? <span className='inline-flex items-center gap-1 px-2 py-0.5 bg-red-50 text-red-600 rounded-full text-xs font-medium'><FiAlertTriangle size={10} /> Low</span>
                      : <span className='inline-flex items-center gap-1 px-2 py-0.5 bg-emerald-50 text-emerald-600 rounded-full text-xs font-medium'>OK</span>
                    }
                  </td>
                  {isAdmin && (
                    <td className='px-4 py-3 text-center' onClick={e => e.stopPropagation()}>
                      <div className='flex items-center justify-center gap-0.5'>
                        <button onClick={() => { setPurchaseItem(item); setPurchaseForm({ quantity: '', price_per_unit: '', notes: '' }) }} className='p-1.5 text-emerald-500 hover:bg-emerald-50 rounded transition' title='Beli / Tambah Stok'><FiShoppingCart size={14} /></button>
                        <button onClick={() => { setAdjustItem(item); setAdjustForm({ new_qty: item.stock_qty, notes: '' }) }} className='p-1.5 text-amber-500 hover:bg-amber-50 rounded transition' title='Sesuaikan Stok'><FiSliders size={14} /></button>
                        <button onClick={() => handleEdit(item)} className='p-1.5 text-blue-500 hover:bg-blue-50 rounded transition' title='Edit Info'><FiEdit2 size={14} /></button>
                        <button onClick={() => handleDelete(item.id)} className='p-1.5 text-red-500 hover:bg-red-50 rounded transition' title='Hapus'><FiTrash2 size={14} /></button>
                      </div>
                    </td>
                  )}
                </tr>
              )})}
            </tbody>
          </table>
        </div>
        {pagination.totalPages > 1 && (
          <div className='flex items-center justify-between px-4 py-3 border-t border-slate-100'>
            <p className='text-xs text-slate-500'>Menampilkan {((page - 1) * 50) + 1}-{Math.min(page * 50, pagination.total)} dari {formatNumber(pagination.total)} item</p>
            <div className='flex gap-1'>
              <button disabled={page <= 1} onClick={() => setPage(p => p - 1)} className='p-1.5 rounded border border-slate-300 disabled:opacity-30 hover:bg-slate-50'><FiChevronLeft size={16} /></button>
              <span className='px-3 py-1.5 text-sm text-slate-600'>{page} / {pagination.totalPages}</span>
              <button disabled={page >= pagination.totalPages} onClick={() => setPage(p => p + 1)} className='p-1.5 rounded border border-slate-300 disabled:opacity-30 hover:bg-slate-50'><FiChevronRight size={16} /></button>
            </div>
          </div>
        )}
      </div>

      { }
      {showForm && (
        <div className='fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4' onClick={() => setShowForm(false)}>
          <div className='bg-white rounded-xl w-full max-w-lg max-h-[90vh] overflow-y-auto' onClick={e => e.stopPropagation()}>
            <div className='sticky top-0 bg-white flex items-center justify-between px-5 py-4 border-b'>
              <h2 className='text-lg font-bold text-slate-800'>{editItem ? 'Edit Material' : 'Tambah Material Baru'}</h2>
              <button onClick={() => setShowForm(false)} className='p-1 hover:bg-slate-100 rounded'><FiX size={20} /></button>
            </div>
            <div className='p-5 space-y-4'>
              <div className='grid grid-cols-2 gap-4'>
                <div>
                  <label className='block text-xs font-medium text-slate-600 mb-1'>Kode (SKU)</label>
                  <input type='text' value={form.material_code} onChange={e => setForm({ ...form, material_code: e.target.value })} className='w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500' placeholder='KBL-001' />
                </div>
                <div>
                  <label className='block text-xs font-medium text-slate-600 mb-1'>Satuan</label>
                  <input type='text' value={form.unit} onChange={e => setForm({ ...form, unit: e.target.value })} className='w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500' placeholder='pcs, meter, kg' />
                </div>
              </div>
              <div>
                <label className='block text-xs font-medium text-slate-600 mb-1'>Nama Material <span className='text-red-500'>*</span></label>
                <input type='text' value={form.material_name} onChange={e => setForm({ ...form, material_name: e.target.value })} className='w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500' placeholder='Kabel NYM 3x2.5mm' />
              </div>
              <div className='grid grid-cols-2 gap-4'>
                <div>
                  <label className='block text-xs font-medium text-slate-600 mb-1'>Kategori</label>
                  <input type='text' value={form.category} onChange={e => setForm({ ...form, category: e.target.value })} className='w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500' placeholder='Kabel, Pipa' list='catList' />
                  <datalist id='catList'>{categories.map(c => <option key={c} value={c} />)}</datalist>
                </div>
                <div>
                  <label className='block text-xs font-medium text-slate-600 mb-1'>Lokasi Gudang</label>
                  <input type='text' value={form.location} onChange={e => setForm({ ...form, location: e.target.value })} className='w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500' placeholder='Gudang A' />
                </div>
              </div>
              <div>
                <label className='block text-xs font-medium text-slate-600 mb-1'>Deskripsi</label>
                <textarea value={form.description} onChange={e => setForm({ ...form, description: e.target.value })} className='w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500' rows={2} placeholder='Deskripsi material...' />
              </div>
              {!editItem && (
                <div className='grid grid-cols-2 gap-4'>
                  <div>
                    <label className='block text-xs font-medium text-slate-600 mb-1'>Jumlah Stok Awal</label>
                    <input type='number' value={form.stock_qty} onChange={e => setForm({ ...form, stock_qty: e.target.value })} className='w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500' min={0} />
                  </div>
                  <div>
                    <label className='block text-xs font-medium text-slate-600 mb-1'>Harga Rata-rata</label>
                    <input type='number' value={form.avg_price} onChange={e => setForm({ ...form, avg_price: e.target.value })} className='w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500' min={0} />
                  </div>
                </div>
              )}
              <div className='grid grid-cols-2 gap-4'>
                <div>
                  <label className='block text-xs font-medium text-slate-600 mb-1'>Stok Minimum (Alert)</label>
                  <input type='number' value={form.min_stock} onChange={e => setForm({ ...form, min_stock: e.target.value })} className='w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500' min={0} />
                </div>
                <div>
                  <label className='block text-xs font-medium text-slate-600 mb-1'>Supplier</label>
                  <input type='text' value={form.supplier} onChange={e => setForm({ ...form, supplier: e.target.value })} className='w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500' placeholder='Nama supplier' />
                </div>
              </div>
            </div>
            <div className='sticky bottom-0 bg-white flex justify-end gap-2 px-5 py-4 border-t'>
              <button onClick={() => setShowForm(false)} className='px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-lg transition'>Batal</button>
              <button onClick={handleSave} className='px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition'>{editItem ? 'Update' : 'Simpan'}</button>
            </div>
          </div>
        </div>
      )}

      { }
      {purchaseItem && (
        <div className='fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4' onClick={() => setPurchaseItem(null)}>
          <div className='bg-white rounded-xl w-full max-w-md' onClick={e => e.stopPropagation()}>
            <div className='flex items-center justify-between px-5 py-4 border-b'>
              <h2 className='text-lg font-bold text-slate-800'>Beli / Tambah Stok</h2>
              <button onClick={() => setPurchaseItem(null)} className='p-1 hover:bg-slate-100 rounded'><FiX size={20} /></button>
            </div>
            <div className='p-5 space-y-4'>
              <div className='bg-slate-50 p-3 rounded-lg'>
                <p className='text-sm font-semibold text-slate-800'>{purchaseItem.material_name}</p>
                <p className='text-xs text-slate-500'>Stok saat ini: {formatNumber(purchaseItem.stock_qty)} {purchaseItem.unit} &middot; Harga avg: {formatCurrency(purchaseItem.avg_price)}</p>
              </div>
              <div>
                <label className='block text-xs font-medium text-slate-600 mb-1'>Jumlah Beli <span className='text-red-500'>*</span></label>
                <input type='number' value={purchaseForm.quantity} onChange={e => setPurchaseForm({ ...purchaseForm, quantity: e.target.value })} className='w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500' min={1} placeholder='100' />
              </div>
              <div>
                <label className='block text-xs font-medium text-slate-600 mb-1'>Harga Per Unit (Rp) <span className='text-red-500'>*</span></label>
                <input type='number' value={purchaseForm.price_per_unit} onChange={e => setPurchaseForm({ ...purchaseForm, price_per_unit: e.target.value })} className='w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500' min={0} placeholder='15000' />
              </div>
              {purchaseForm.quantity && purchaseForm.price_per_unit && (
                <div className='bg-blue-50 p-3 rounded-lg text-sm'>
                  <p className='text-blue-700'>Total: <strong>{formatCurrency(parseFloat(purchaseForm.quantity) * parseFloat(purchaseForm.price_per_unit))}</strong></p>
                  <p className='text-blue-600 text-xs mt-1'>Stok baru: {formatNumber(parseFloat(purchaseItem.stock_qty) + parseFloat(purchaseForm.quantity))} {purchaseItem.unit}</p>
                </div>
              )}
              <div>
                <label className='block text-xs font-medium text-slate-600 mb-1'>Catatan</label>
                <input type='text' value={purchaseForm.notes} onChange={e => setPurchaseForm({ ...purchaseForm, notes: e.target.value })} className='w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500' placeholder='Beli dari toko XYZ' />
              </div>
            </div>
            <div className='flex justify-end gap-2 px-5 py-4 border-t'>
              <button onClick={() => setPurchaseItem(null)} className='px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-lg transition'>Batal</button>
              <button onClick={handlePurchase} className='px-4 py-2 text-sm bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition'>Tambah Stok</button>
            </div>
          </div>
        </div>
      )}

      { }
      {adjustItem && (
        <div className='fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4' onClick={() => setAdjustItem(null)}>
          <div className='bg-white rounded-xl w-full max-w-md' onClick={e => e.stopPropagation()}>
            <div className='flex items-center justify-between px-5 py-4 border-b'>
              <h2 className='text-lg font-bold text-slate-800'>Sesuaikan Stok (Stock Opname)</h2>
              <button onClick={() => setAdjustItem(null)} className='p-1 hover:bg-slate-100 rounded'><FiX size={20} /></button>
            </div>
            <div className='p-5 space-y-4'>
              <div className='bg-slate-50 p-3 rounded-lg'>
                <p className='text-sm font-semibold text-slate-800'>{adjustItem.material_name}</p>
                <p className='text-xs text-slate-500'>Stok tercatat: <strong>{formatNumber(adjustItem.stock_qty)}</strong> {adjustItem.unit}</p>
              </div>
              <div>
                <label className='block text-xs font-medium text-slate-600 mb-1'>Jumlah Stok Sebenarnya <span className='text-red-500'>*</span></label>
                <input type='number' value={adjustForm.new_qty} onChange={e => setAdjustForm({ ...adjustForm, new_qty: e.target.value })} className='w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500' min={0} />
              </div>
              {adjustForm.new_qty !== '' && parseFloat(adjustForm.new_qty) !== parseFloat(adjustItem.stock_qty) && (
                <div className={`p-3 rounded-lg text-sm ${parseFloat(adjustForm.new_qty) < parseFloat(adjustItem.stock_qty) ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700'}`}>
                  Selisih: <strong>{parseFloat(adjustForm.new_qty) > parseFloat(adjustItem.stock_qty) ? '+' : ''}{formatNumber(parseFloat(adjustForm.new_qty) - parseFloat(adjustItem.stock_qty))}</strong> {adjustItem.unit}
                </div>
              )}
              <div>
                <label className='block text-xs font-medium text-slate-600 mb-1'>Alasan / Catatan</label>
                <input type='text' value={adjustForm.notes} onChange={e => setAdjustForm({ ...adjustForm, notes: e.target.value })} className='w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500' placeholder='Stock opname, rusak, dll' />
              </div>
            </div>
            <div className='flex justify-end gap-2 px-5 py-4 border-t'>
              <button onClick={() => setAdjustItem(null)} className='px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-lg transition'>Batal</button>
              <button onClick={handleAdjust} className='px-4 py-2 text-sm bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition'>Simpan Penyesuaian</button>
            </div>
          </div>
        </div>
      )}

      { }
      {showImport && (
        <div className='fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4' onClick={() => setShowImport(false)}>
          <div className='bg-white rounded-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto' onClick={e => e.stopPropagation()}>
            <div className='sticky top-0 bg-white flex items-center justify-between px-5 py-4 border-b z-10'>
              <h2 className='text-lg font-bold text-slate-800'>Import dari Excel</h2>
              <button onClick={() => setShowImport(false)} className='p-1 hover:bg-slate-100 rounded'><FiX size={20} /></button>
            </div>
            <div className='p-5'>
              {importStep === 1 && (
                <div className='space-y-4'>
                  <div className='bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-700'>
                    <p className='font-semibold mb-1'>Petunjuk:</p>
                    <ol className='list-decimal ml-4 space-y-1'>
                      <li>Download template jika belum punya format</li>
                      <li>Isi data material di file Excel</li>
                      <li>Upload file (.xlsx)</li>
                      <li>Mapping kolom → review → import</li>
                    </ol>
                    <p className='mt-2'>Jika nama/kode sudah ada, data akan di-<strong>update</strong>.</p>
                  </div>
                  <button onClick={handleExportTemplate} className='flex items-center gap-1.5 px-4 py-2 bg-slate-100 text-slate-700 text-sm rounded-lg hover:bg-slate-200 transition'>
                    <FiDownload size={16} /> Download Template
                  </button>
                  <div className='border-2 border-dashed border-slate-300 rounded-xl p-8 text-center hover:border-blue-400 transition'>
                    <input type='file' accept='.xlsx,.xls' onChange={handleFileUpload} className='hidden' id='excel-upload' />
                    <label htmlFor='excel-upload' className='cursor-pointer'>
                      <FiUpload className='mx-auto mb-3 text-slate-400' size={40} />
                      <p className='text-sm text-slate-600 font-medium'>Klik untuk upload file Excel</p>
                      <p className='text-xs text-slate-400 mt-1'>.xlsx atau .xls (maks 5000 baris)</p>
                    </label>
                  </div>
                </div>
              )}
              {importStep === 2 && (
                <div className='space-y-4'>
                  <div className='bg-amber-50 border border-amber-200 rounded-lg p-3 text-sm text-amber-700'>
                    <strong>{importData.length}</strong> baris ditemukan. Pastikan mapping benar.
                  </div>
                  <div className='space-y-3'>
                    <h3 className='font-semibold text-slate-700 text-sm'>Mapping Kolom</h3>
                    {mappingFields.map(f => (
                      <div key={f.key} className='flex items-center gap-3'>
                        <label className='w-40 text-sm text-slate-600 shrink-0'>{f.label} {f.required && <span className='text-red-500'>*</span>}</label>
                        <select value={importMapping[f.key] || ''} onChange={e => setImportMapping({ ...importMapping, [f.key]: e.target.value ? parseInt(e.target.value) : '' })}
                          className={`flex-1 px-3 py-1.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 ${f.required && !importMapping[f.key] ? 'border-red-300 bg-red-50' : 'border-slate-300'}`}>
                          <option value=''>-- Lewati --</option>
                          {importHeaders.map(h => <option key={h.col} value={h.col}>{h.name}</option>)}
                        </select>
                      </div>
                    ))}
                  </div>
                  <div>
                    <h3 className='font-semibold text-slate-700 text-sm mb-2'>Preview (5 baris pertama)</h3>
                    <div className='overflow-x-auto border rounded-lg'>
                      <table className='w-full text-xs'>
                        <thead><tr className='bg-slate-50'>
                          <th className='px-2 py-1.5 text-left'>#</th>
                          {mappingFields.filter(f => importMapping[f.key]).map(f => <th key={f.key} className='px-2 py-1.5 text-left'>{f.label}</th>)}
                        </tr></thead>
                        <tbody>
                          {importData.slice(0, 5).map((row, i) => (
                            <tr key={i} className='border-t'>
                              <td className='px-2 py-1.5 text-slate-400'>{i + 1}</td>
                              {mappingFields.filter(f => importMapping[f.key]).map(f => <td key={f.key} className='px-2 py-1.5 truncate max-w-[150px]'>{row[importMapping[f.key]] !== undefined ? String(row[importMapping[f.key]]) : '-'}</td>)}
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              )}
            </div>
            <div className='sticky bottom-0 bg-white flex justify-between px-5 py-4 border-t'>
              <div>{importStep === 2 && <button onClick={() => setImportStep(1)} className='px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-lg'><FiChevronLeft className='inline mr-1' size={14} />Kembali</button>}</div>
              <div className='flex gap-2'>
                <button onClick={() => setShowImport(false)} className='px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-lg'>Batal</button>
                {importStep === 2 && <button onClick={handleImportSubmit} disabled={importing || !importMapping.material_name} className='px-4 py-2 text-sm bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 disabled:opacity-50'>
                  {importing ? 'Mengimport...' : `Import ${importData.length} Data`}
                </button>}
              </div>
            </div>
          </div>
        </div>
      )}

      { }
      {showDetail && <DetailModal item={showDetail} onClose={() => setShowDetail(null)} authFetch={authFetch} />}

      { }
      {showMovements && (
        <div className='fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4' onClick={() => setShowMovements(false)}>
          <div className='bg-white rounded-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto' onClick={e => e.stopPropagation()}>
            <div className='sticky top-0 bg-white flex items-center justify-between px-5 py-4 border-b z-10'>
              <h2 className='text-lg font-bold text-slate-800'>Riwayat Pergerakan Stok</h2>
              <button onClick={() => setShowMovements(false)} className='p-1 hover:bg-slate-100 rounded'><FiX size={20} /></button>
            </div>
            <div className='p-5'>
              {movements.length === 0
                ? <p className='text-center text-slate-400 py-8'>Belum ada riwayat pergerakan</p>
                : <div className='space-y-2'>{movements.map(m => (
                    <div key={m.id} className='flex items-start gap-3 p-3 rounded-lg border border-slate-100 hover:bg-slate-50'>
                      <div className={`p-1.5 rounded-full shrink-0 ${m.movement_type === 'in' || m.movement_type === 'return' ? 'bg-emerald-100 text-emerald-600' : m.movement_type === 'out' ? 'bg-red-100 text-red-600' : 'bg-amber-100 text-amber-600'}`}>
                        {m.movement_type === 'in' || m.movement_type === 'return' ? <FiTrendingUp size={14} /> : m.movement_type === 'out' ? <FiTrendingDown size={14} /> : <FiRefreshCw size={14} />}
                      </div>
                      <div className='flex-1 min-w-0'>
                        <p className='text-sm font-medium text-slate-800'>{m.material_name}</p>
                        <p className='text-xs text-slate-500'>{m.notes || '-'}</p>
                        <p className='text-xs text-slate-400 mt-1'>{m.created_by_name || 'System'} &middot; {new Date(m.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
                      </div>
                      <div className='text-right shrink-0'>
                        <p className={`text-sm font-bold ${m.movement_type === 'out' ? 'text-red-600' : 'text-emerald-600'}`}>{m.movement_type === 'out' ? '-' : '+'}{formatNumber(m.quantity)} {m.unit}</p>
                        <p className='text-xs text-slate-400'>{formatNumber(m.stock_before)} → {formatNumber(m.stock_after)}</p>
                        {m.price_per_unit > 0 && <p className='text-xs text-slate-400'>@ {formatCurrency(m.price_per_unit)}</p>}
                      </div>
                    </div>
                  ))}</div>
              }
            </div>
          </div>
        </div>
      )}
    </div>
  )
}



function DetailModal({ item, onClose, authFetch }) {
  const [detail, setDetail] = useState(null)
  const [loading, setLoading] = useState(true)
  const API = import.meta.env.VITE_API_BASE || '/api'

  useEffect(() => {
    (async () => {
      try {
        const res = await authFetch(`${API}/stock/${item.id}`)
        const data = await res.json()
        if (data.success) setDetail(data.data)
      } catch (e) {   }
      setLoading(false)
    })()
  }, [item.id])

  const d = detail || item

  return (
    <div className='fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4' onClick={onClose}>
      <div className='bg-white rounded-xl w-full max-w-lg max-h-[90vh] overflow-y-auto' onClick={e => e.stopPropagation()}>
        <div className='sticky top-0 bg-white flex items-center justify-between px-5 py-4 border-b z-10'>
          <h2 className='text-lg font-bold text-slate-800'>Detail Material</h2>
          <button onClick={onClose} className='p-1 hover:bg-slate-100 rounded'><FiX size={20} /></button>
        </div>
        <div className='p-5 space-y-4'>
          {loading ? (
            <div className='flex justify-center py-8'><div className='w-6 h-6 border-2 border-slate-300 border-t-slate-600 rounded-full animate-spin' /></div>
          ) : (
            <>
              <div className='grid grid-cols-2 gap-4'>
                <div><p className='text-xs text-slate-500'>Kode</p><p className='font-mono text-sm'>{d.material_code || '-'}</p></div>
                <div><p className='text-xs text-slate-500'>Satuan</p><p className='text-sm'>{d.unit}</p></div>
              </div>
              <div><p className='text-xs text-slate-500'>Nama Material</p><p className='text-sm font-semibold'>{d.material_name}</p></div>
              {d.description && <div><p className='text-xs text-slate-500'>Deskripsi</p><p className='text-sm'>{d.description}</p></div>}
              <div className='grid grid-cols-2 gap-4'>
                <div><p className='text-xs text-slate-500'>Kategori</p><p className='text-sm'>{d.category || '-'}</p></div>
                <div><p className='text-xs text-slate-500'>Supplier</p><p className='text-sm'>{d.supplier || '-'}</p></div>
              </div>
              <div className='grid grid-cols-2 gap-3'>
                <div className='bg-blue-50 p-3 rounded-lg text-center'>
                  <p className='text-xs text-blue-600'>Stok Saat Ini</p>
                  <p className='text-xl font-bold text-blue-700'>{formatNumber(d.stock_qty)}</p>
                </div>
                <div className='bg-amber-50 p-3 rounded-lg text-center'>
                  <p className='text-xs text-amber-600'>Stok Minimum</p>
                  <p className='text-xl font-bold text-amber-700'>{formatNumber(d.min_stock)}</p>
                </div>
              </div>
              <div className='grid grid-cols-3 gap-3'>
                <div className='bg-slate-50 p-3 rounded-lg text-center'>
                  <p className='text-xs text-slate-500'>Harga Avg</p>
                  <p className='text-sm font-bold text-slate-800'>{formatCurrency(d.avg_price)}</p>
                </div>
                <div className='bg-slate-50 p-3 rounded-lg text-center'>
                  <p className='text-xs text-slate-500'>Harga Terakhir</p>
                  <p className='text-sm font-bold text-slate-800'>{formatCurrency(d.last_price)}</p>
                </div>
                <div className='bg-emerald-50 p-3 rounded-lg text-center'>
                  <p className='text-xs text-emerald-600'>Total Nilai</p>
                  <p className='text-sm font-bold text-emerald-700'>{formatCurrency(d.total_value)}</p>
                </div>
              </div>
              {detail?.movements?.length > 0 && (
                <div>
                  <h3 className='text-sm font-semibold text-slate-700 mb-2'>Riwayat Terakhir</h3>
                  <div className='space-y-1.5'>{detail.movements.slice(0, 5).map(m => (
                    <div key={m.id} className='flex items-center justify-between text-xs p-2 rounded bg-slate-50'>
                      <div className='flex items-center gap-2'>
                        <span className={`w-1.5 h-1.5 rounded-full ${m.movement_type === 'out' ? 'bg-red-500' : 'bg-emerald-500'}`} />
                        <span className='text-slate-600'>{m.notes || m.movement_type}</span>
                      </div>
                      <span className={`font-semibold ${m.movement_type === 'out' ? 'text-red-600' : 'text-emerald-600'}`}>
                        {m.movement_type === 'out' ? '-' : '+'}{formatNumber(m.quantity)}
                      </span>
                    </div>
                  ))}</div>
                </div>
              )}
            </>
          )}
        </div>
      </div>
    </div>
  )
}
