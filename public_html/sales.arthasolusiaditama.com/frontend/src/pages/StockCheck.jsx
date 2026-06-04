import React, { useState, useEffect, useCallback, useRef } from 'react'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import { FiClipboard, FiPlus, FiUpload, FiSearch, FiChevronLeft, FiChevronRight, FiX, FiCheck, FiSkipForward, FiArrowLeft, FiTrash2, FiCheckCircle, FiAlertTriangle, FiDatabase, FiFileText, FiPlay, FiDownload, FiList } from 'react-icons/fi'
import * as ExcelJS from 'exceljs'
import { saveAs } from 'file-saver'

const API_BASE = import.meta.env.VITE_API_BASE || '/api'
const formatNumber = (val) => new Intl.NumberFormat('id-ID').format(val || 0)
const formatCurrency = (val) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0)



export default function StockCheck() {
  const { user, authFetch } = useAuth()
  const toast = useToast()
  const showToast = (msg, type = 'info') => toast[type] ? toast[type](msg) : toast.info(msg)
  const isAdmin = user?.role === 'administrator' || user?.role === 'direktur'

  const [view, setView] = useState('list') 
  const [sessions, setSessions] = useState([])
  const [loading, setLoading] = useState(true)
  const [activeSession, setActiveSession] = useState(null)

  
  
  
  const fetchSessions = useCallback(async () => {
    setLoading(true)
    try {
      const res = await authFetch(`${API_BASE}/stock-check`)
      const data = await res.json()
      if (data.success) setSessions(data.data)
    } catch (e) { showToast('Gagal memuat data', 'error') }
    finally { setLoading(false) }
  }, [authFetch])

  useEffect(() => { fetchSessions() }, [fetchSessions])

  
  
  
  if (view === 'create') return <CreateSession authFetch={authFetch} showToast={showToast} onBack={() => { setView('list'); fetchSessions() }} />
  if (view === 'check') return <CheckMode authFetch={authFetch} showToast={showToast} session={activeSession} isAdmin={isAdmin} onBack={() => { setView('list'); fetchSessions() }} />
  if (view === 'report') return <ReportView authFetch={authFetch} showToast={showToast} session={activeSession} isAdmin={isAdmin} onBack={() => { setView('list'); fetchSessions() }} />

  return (
    <div className='space-y-4'>
      { }
      <div className='flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3'>
        <div>
          <h1 className='text-xl font-bold text-slate-800 flex items-center gap-2'><FiClipboard /> Stock Opname</h1>
          <p className='text-sm text-slate-500'>Pengecekan material berkala</p>
        </div>
        {isAdmin && (
          <button onClick={() => setView('create')} className='flex items-center gap-1.5 px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition'>
            <FiPlus size={16} /> Pengecekan Baru
          </button>
        )}
      </div>

      { }
      {loading ? (
        <div className='bg-white rounded-xl border border-slate-200 p-12 text-center text-slate-400'>
          <div className='w-6 h-6 border-2 border-slate-300 border-t-slate-600 rounded-full animate-spin mx-auto mb-2' />Memuat...
        </div>
      ) : sessions.length === 0 ? (
        <div className='bg-white rounded-xl border border-slate-200 p-12 text-center text-slate-400'>
          <FiClipboard className='mx-auto mb-2' size={32} />Belum ada sesi pengecekan
        </div>
      ) : (
        <div className='space-y-3'>
          {sessions.map(s => {
            const pct = s.total_items > 0 ? Math.round((s.checked_items / s.total_items) * 100) : 0
            return (
              <div key={s.id} className='bg-white rounded-xl border border-slate-200 p-4 space-y-3'>
                <div className='flex items-start justify-between gap-2'>
                  <div className='min-w-0'>
                    <p className='font-semibold text-slate-800 truncate'>{s.check_name}</p>
                    <p className='text-xs text-slate-400 mt-0.5'>
                      {new Date(s.check_date).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })}
                      {' · '}{s.created_by_name}
                    </p>
                    {s.notes && <p className='text-xs text-slate-400 mt-0.5 truncate'>{s.notes}</p>}
                  </div>
                  <div className='flex items-center gap-1.5 shrink-0'>
                    <span className={`px-2 py-0.5 rounded text-[10px] font-medium ${s.source_type === 'excel' ? 'bg-emerald-50 text-emerald-600' : 'bg-blue-50 text-blue-600'}`}>
                      {s.source_type === 'excel' ? 'Excel' : 'Stock'}
                    </span>
                    <span className={`px-2 py-0.5 rounded-full text-[10px] font-medium ${
                      s.status === 'completed' ? 'bg-emerald-50 text-emerald-600' :
                      s.status === 'in_progress' ? 'bg-amber-50 text-amber-600' : 'bg-slate-100 text-slate-500'}`}>
                      {s.status === 'completed' ? 'Selesai' : s.status === 'in_progress' ? 'Berjalan' : 'Draft'}
                    </span>
                  </div>
                </div>

                { }
                <div>
                  <div className='flex items-center justify-between mb-1'>
                    <span className='text-xs text-slate-500'>{s.checked_items}/{s.total_items} item</span>
                    <div className='flex items-center gap-2'>
                      {s.discrepancy_count > 0 && <span className='text-xs text-red-600 font-medium'>{s.discrepancy_count} selisih</span>}
                      <span className='text-xs text-slate-500'>{pct}%</span>
                    </div>
                  </div>
                  <div className='w-full h-2 bg-slate-100 rounded-full overflow-hidden'>
                    <div className='h-full bg-blue-500 rounded-full transition-all' style={{ width: `${pct}%` }} />
                  </div>
                </div>

                { }
                <div className='flex items-center gap-2 pt-1'>
                  {s.status !== 'completed' && s.total_items > 0 && isAdmin && (
                    <button onClick={() => { setActiveSession(s); setView('check') }} className='flex-1 flex items-center justify-center gap-1.5 px-3 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 active:bg-blue-800 transition'>
                      <FiPlay size={14} /> Mulai Cek
                    </button>
                  )}
                  <button onClick={() => { setActiveSession(s); setView('report') }} className={`${s.status === 'completed' || !isAdmin ? 'flex-1' : ''} flex items-center justify-center gap-1.5 px-3 py-2.5 border border-slate-300 text-slate-600 text-sm rounded-lg hover:bg-slate-50 active:bg-slate-100 transition`}>
                    <FiFileText size={14} /> Laporan
                  </button>
                  {isAdmin && s.status !== 'completed' && (
                    <button onClick={async () => {
                      if (!confirm('Hapus sesi pengecekan ini?')) return
                      try {
                        const res = await authFetch(`${API_BASE}/stock-check/${s.id}`, { method: 'DELETE' })
                        const data = await res.json()
                        if (data.success) { showToast(data.message, 'success'); fetchSessions() }
                        else showToast(data.message, 'error')
                      } catch (e) { showToast('Gagal menghapus', 'error') }
                    }} className='p-2.5 text-red-500 border border-red-200 hover:bg-red-50 active:bg-red-100 rounded-lg transition'>
                      <FiTrash2 size={14} />
                    </button>
                  )}
                </div>
              </div>
            )
          })}
        </div>
      )}
    </div>
  )
}



function CreateSession({ authFetch, showToast, onBack }) {
  const [name, setName] = useState('')
  const [notes, setNotes] = useState('')
  const [source, setSource] = useState('stock') 
  const [creating, setCreating] = useState(false)

  
  const [excelData, setExcelData] = useState([])
  const [excelHeaders, setExcelHeaders] = useState([])
  const [mapping, setMapping] = useState({})
  const [step, setStep] = useState(1) 

  const mappingFields = [
    { key: 'material_name', label: 'Nama Produk', required: true },
    { key: 'material_code', label: 'Kode Produk (SKU)' },
    { key: 'unit', label: 'Satuan' },
    { key: 'category', label: 'Kategori' },
    { key: 'description', label: 'Deskripsi' },
    { key: 'recorded_qty', label: 'Jumlah Yang Tercatat', required: true },
    { key: 'actual_qty', label: 'Jumlah Sebenarnya' },
    { key: 'avg_price', label: 'Harga rata-rata' },
  ]

  const handleFileUpload = async (e) => {
    const file = e.target.files[0]
    if (!file) return
    try {
      const wb = new ExcelJS.Workbook()
      await wb.xlsx.load(await file.arrayBuffer())
      const ws = wb.worksheets[0]
      if (!ws || ws.rowCount < 2) return showToast('File Excel kosong', 'error')

      
      const knownKeywords = ['nama', 'name', 'produk', 'item', 'barang', 'kode', 'code', 'sku', 'satuan', 'unit', 'kategori', 'category', 'stok', 'stock', 'harga', 'price', 'qty', 'jumlah', 'tercatat', 'sebenarnya', 'deskripsi', 'description']
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
      setExcelHeaders(headers)

      
      const autoMap = {}
      const keywords = {
        material_name: ['nama', 'name', 'produk', 'item', 'barang'],
        material_code: ['kode', 'code', 'sku'],
        unit: ['satuan', 'unit', 'uom'],
        category: ['kategori', 'category', 'jenis'],
        description: ['deskripsi', 'description', 'keterangan'],
        recorded_qty: ['tercatat'],
        actual_qty: ['sebenarnya', 'aktual', 'actual', 'fisik'],
        avg_price: ['harga', 'price', 'rata'],
      }
      for (const h of headers) {
        const lower = h.name.toLowerCase()
        for (const [field, kws] of Object.entries(keywords)) {
          if (kws.some(k => lower.includes(k)) && !autoMap[field]) { autoMap[field] = h.col; break }
        }
      }
      setMapping(autoMap)

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

      setExcelData(rows)
      setStep(2)
      showToast(`${rows.length} baris data ditemukan (header di baris ${headerRowNum})`, 'success')
    } catch (err) { showToast('Gagal membaca file Excel', 'error') }
    e.target.value = ''
  }

  const handleCreate = async () => {
    if (!name.trim()) return showToast('Nama pengecekan wajib diisi', 'error')
    if (source === 'excel' && step < 2) return showToast('Upload file Excel terlebih dahulu', 'error')
    if (source === 'excel' && !mapping.material_name) return showToast('Mapping Nama Produk wajib', 'error')
    if (source === 'excel' && !mapping.recorded_qty) return showToast('Mapping Jumlah Tercatat wajib', 'error')

    setCreating(true)
    try {
      
      const res = await authFetch(`${API_BASE}/stock-check`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ check_name: name, notes, source })
      })
      const data = await res.json()
      if (!data.success) { showToast(data.message, 'error'); setCreating(false); return }

      const checkId = data.data.id

      
      if (source === 'excel' && excelData.length > 0) {
        const mappedItems = excelData.map(row => {
          const item = {}
          for (const [field, col] of Object.entries(mapping)) {
            if (col) item[field] = row[col] || ''
          }
          return item
        }).filter(i => i.material_name && i.material_name.toString().trim())

        if (mappedItems.length > 0) {
          const importRes = await authFetch(`${API_BASE}/stock-check/${checkId}/import`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ items: mappedItems })
          })
          const importData = await importRes.json()
          if (!importData.success) {
            showToast(importData.message, 'error')
            setCreating(false)
            return
          }
        }
      }

      showToast('Sesi pengecekan berhasil dibuat!', 'success')
      onBack()
    } catch (e) { showToast('Gagal membuat sesi', 'error') }
    finally { setCreating(false) }
  }

  return (
    <div className='space-y-4'>
      <div className='flex items-center gap-3'>
        <button onClick={onBack} className='p-2 hover:bg-slate-100 rounded-lg transition'><FiArrowLeft size={20} /></button>
        <div>
          <h1 className='text-xl font-bold text-slate-800'>Pengecekan Baru</h1>
          <p className='text-sm text-slate-500'>Buat sesi stock opname baru</p>
        </div>
      </div>

      <div className='bg-white rounded-xl border border-slate-200 p-5 space-y-5 max-w-2xl'>
        <div>
          <label className='block text-sm font-medium text-slate-700 mb-1'>Nama Pengecekan <span className='text-red-500'>*</span></label>
          <input type='text' value={name} onChange={e => setName(e.target.value)} placeholder='Opname Bulanan - April 2026'
            className='w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500' />
        </div>

        <div>
          <label className='block text-sm font-medium text-slate-700 mb-1'>Catatan</label>
          <textarea value={notes} onChange={e => setNotes(e.target.value)} placeholder='Catatan opsional...' rows={2}
            className='w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500' />
        </div>

        <div>
          <label className='block text-sm font-medium text-slate-700 mb-2'>Sumber Data</label>
          <div className='grid grid-cols-2 gap-3'>
            <button onClick={() => { setSource('stock'); setStep(1); setExcelData([]) }}
              className={`p-4 rounded-xl border-2 text-left transition ${source === 'stock' ? 'border-blue-500 bg-blue-50' : 'border-slate-200 hover:border-slate-300'}`}>
              <FiDatabase className={`mb-2 ${source === 'stock' ? 'text-blue-600' : 'text-slate-400'}`} size={24} />
              <p className='font-semibold text-sm text-slate-800'>Dari Data Stock</p>
              <p className='text-xs text-slate-500 mt-1'>Ambil semua material yang sudah ada di sistem stock</p>
            </button>
            <button onClick={() => { setSource('excel'); setStep(1) }}
              className={`p-4 rounded-xl border-2 text-left transition ${source === 'excel' ? 'border-emerald-500 bg-emerald-50' : 'border-slate-200 hover:border-slate-300'}`}>
              <FiUpload className={`mb-2 ${source === 'excel' ? 'text-emerald-600' : 'text-slate-400'}`} size={24} />
              <p className='font-semibold text-sm text-slate-800'>Upload Excel</p>
              <p className='text-xs text-slate-500 mt-1'>Import data dari file Excel (header otomatis dideteksi)</p>
            </button>
          </div>
        </div>

        { }
        {source === 'excel' && step === 1 && (
          <div className='space-y-3'>
            <div className='bg-blue-50 border border-blue-200 rounded-lg p-3 text-sm text-blue-700'>
              <p className='font-semibold mb-1'>Format Excel:</p>
              <p>Sistem otomatis mendeteksi baris header. Baris metadata di atas header (Tipe, Tanggal, Kode Akun, dll) akan otomatis dilewati.</p>
            </div>
            <div className='border-2 border-dashed border-slate-300 rounded-xl p-6 text-center hover:border-blue-400 transition'>
              <input type='file' accept='.xlsx,.xls' onChange={handleFileUpload} className='hidden' id='check-excel-upload' />
              <label htmlFor='check-excel-upload' className='cursor-pointer'>
                <FiUpload className='mx-auto mb-2 text-slate-400' size={32} />
                <p className='text-sm text-slate-600 font-medium'>Klik untuk upload file Excel</p>
                <p className='text-xs text-slate-400 mt-1'>.xlsx atau .xls</p>
              </label>
            </div>
          </div>
        )}

        { }
        {source === 'excel' && step === 2 && (
          <div className='space-y-4'>
            <div className='bg-amber-50 border border-amber-200 rounded-lg p-3 text-sm text-amber-700'>
              <strong>{excelData.length}</strong> baris ditemukan. Pastikan mapping benar.
            </div>
            <div className='space-y-3'>
              <h3 className='font-semibold text-slate-700 text-sm'>Mapping Kolom</h3>
              {mappingFields.map(f => (
                <div key={f.key} className='flex items-center gap-3'>
                  <label className='w-44 text-sm text-slate-600 shrink-0'>{f.label} {f.required && <span className='text-red-500'>*</span>}</label>
                  <select value={mapping[f.key] || ''} onChange={e => setMapping({ ...mapping, [f.key]: e.target.value ? parseInt(e.target.value) : '' })}
                    className={`flex-1 px-3 py-1.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 ${f.required && !mapping[f.key] ? 'border-red-300 bg-red-50' : 'border-slate-300'}`}>
                    <option value=''>-- Lewati --</option>
                    {excelHeaders.map(h => <option key={h.col} value={h.col}>{h.name}</option>)}
                  </select>
                </div>
              ))}
            </div>
            { }
            <div>
              <h3 className='font-semibold text-slate-700 text-sm mb-2'>Preview (5 baris pertama)</h3>
              <div className='overflow-x-auto border rounded-lg'>
                <table className='w-full text-xs'>
                  <thead><tr className='bg-slate-50'>
                    <th className='px-2 py-1.5 text-left'>#</th>
                    {mappingFields.filter(f => mapping[f.key]).map(f => <th key={f.key} className='px-2 py-1.5 text-left'>{f.label}</th>)}
                  </tr></thead>
                  <tbody>
                    {excelData.slice(0, 5).map((row, i) => (
                      <tr key={i} className='border-t'>
                        <td className='px-2 py-1.5 text-slate-400'>{i + 1}</td>
                        {mappingFields.filter(f => mapping[f.key]).map(f => (
                          <td key={f.key} className='px-2 py-1.5 truncate max-w-[150px]'>{row[mapping[f.key]] !== undefined ? String(row[mapping[f.key]]) : '-'}</td>
                        ))}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
            <button onClick={() => { setStep(1); setExcelData([]); setExcelHeaders([]); setMapping({}) }} className='text-sm text-slate-500 hover:text-slate-700'>
              <FiChevronLeft className='inline mr-1' size={14} />Ganti file
            </button>
          </div>
        )}

        <div className='flex justify-end gap-2 pt-3 border-t'>
          <button onClick={onBack} className='px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-lg transition'>Batal</button>
          <button onClick={handleCreate} disabled={creating}
            className='px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 transition'>
            {creating ? 'Membuat...' : 'Buat Pengecekan'}
          </button>
        </div>
      </div>
    </div>
  )
}



function CheckMode({ authFetch, showToast, session, isAdmin, onBack }) {
  const [currentItem, setCurrentItem] = useState(null)
  const [progress, setProgress] = useState({ total: 0, checked: 0 })
  const [loading, setLoading] = useState(true)
  const [actualQty, setActualQty] = useState('')
  const [itemNotes, setItemNotes] = useState('')
  const [minStock, setMinStock] = useState('')
  const [adjustmentReason, setAdjustmentReason] = useState('')
  const [adjustmentDetail, setAdjustmentDetail] = useState('')
  const [purchaseStore, setPurchaseStore] = useState('')
  const [purchasePrice, setPurchasePrice] = useState('')
  const [showNotes, setShowNotes] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [flash, setFlash] = useState(null) 
  const [suppliers, setSuppliers] = useState([])
  const [sortBy, setSortBy] = useState('default') 
  const [showItemList, setShowItemList] = useState(false)
  const [itemListData, setItemListData] = useState([])
  const [itemListSearch, setItemListSearch] = useState('')
  const [itemListFilter, setItemListFilter] = useState('all') 
  const [itemListLoading, setItemListLoading] = useState(false)
  const inputRef = useRef(null)
  const searchInputRef = useRef(null)

  useEffect(() => {
    authFetch(`${API_BASE}/suppliers`).then(r => r.json()).then(d => {
      if (d.success) setSuppliers(d.data.filter(s => s.is_active))
    }).catch(() => {})
  }, [authFetch])

  const fetchNext = useCallback(async () => {
    setLoading(true)
    try {
      const res = await authFetch(`${API_BASE}/stock-check/${session.id}/next${sortBy !== 'default' ? `?sort=${sortBy}` : ''}`)
      const data = await res.json()
      if (data.success) {
        setCurrentItem(data.data)
        setProgress(data.progress)
        setActualQty('')
        setItemNotes('')
        setMinStock(data.data?.min_stock != null ? String(data.data.min_stock) : '')
        setAdjustmentReason('')
        setAdjustmentDetail('')
        setPurchaseStore('')
        setPurchasePrice('')
        setShowNotes(false)
      }
    } catch (e) { showToast('Gagal memuat item', 'error') }
    finally { setLoading(false) }
  }, [authFetch, session.id, sortBy])

  useEffect(() => { fetchNext() }, [fetchNext])
  useEffect(() => { if (currentItem && inputRef.current && !showItemList) setTimeout(() => inputRef.current?.focus(), 100) }, [currentItem, showItemList])

  
  const fetchItemList = useCallback(async () => {
    setItemListLoading(true)
    try {
      const res = await authFetch(`${API_BASE}/stock-check/${session.id}?limit=10000`)
      const d = await res.json()
      if (d.success) setItemListData(d.data.items || [])
    } catch (e) { showToast('Gagal memuat daftar item', 'error') }
    finally { setItemListLoading(false) }
  }, [authFetch, session.id])

  useEffect(() => { if (showItemList) fetchItemList() }, [showItemList, fetchItemList])

  const handleJumpToItem = async (itemId) => {
    setShowItemList(false)
    setLoading(true)
    try {
      const res = await authFetch(`${API_BASE}/stock-check/${session.id}/items/${itemId}`)
      const data = await res.json()
      if (data.success) {
        const item = data.data
        setCurrentItem(item)
        setProgress(data.progress)
        
        setActualQty(item.status === 'checked' && item.actual_qty != null ? String(item.actual_qty) : '')
        setItemNotes(item.notes || '')
        setMinStock(item.min_stock != null ? String(item.min_stock) : '')
        setAdjustmentReason(item.adjustment_reason || '')
        setAdjustmentDetail(item.adjustment_detail || '')
        setPurchaseStore(item.purchase_store || '')
        setPurchasePrice(item.purchase_price != null ? String(item.purchase_price) : '')
        setShowNotes(!!item.notes)
      } else showToast(data.message, 'error')
    } catch (e) { showToast('Gagal memuat item', 'error') }
    finally { setLoading(false) }
  }

  const filteredItemList = itemListData.filter(i => {
    const matchSearch = !itemListSearch || 
      (i.material_name || '').toLowerCase().includes(itemListSearch.toLowerCase()) ||
      (i.material_code || '').toLowerCase().includes(itemListSearch.toLowerCase())
    const matchFilter = itemListFilter === 'all' ? true :
      itemListFilter === 'pending' ? i.status === 'pending' :
      itemListFilter === 'checked' ? i.status === 'checked' :
      itemListFilter === 'skipped' ? i.status === 'skipped' : true
    return matchSearch && matchFilter
  })

  const handleSubmit = async (skip = false, quickSame = false) => {
    const qtyToSubmit = quickSame ? String(parseFloat(currentItem.recorded_qty) || 0) : actualQty
    if (!skip && !quickSame && (actualQty === '' || isNaN(parseFloat(actualQty)) || parseFloat(actualQty) < 0)) {
      return showToast('Masukkan jumlah sebenarnya', 'error')
    }
    setSubmitting(true)
    try {
      const res = await authFetch(`${API_BASE}/stock-check/${session.id}/items/${currentItem.id}${sortBy !== 'default' ? `?sort=${sortBy}` : ''}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(skip ? { skip: true, notes: itemNotes } : { 
          actual_qty: parseFloat(qtyToSubmit), 
          notes: itemNotes, 
          min_stock: minStock !== '' ? parseFloat(minStock) : undefined,
          adjustment_reason: adjustmentReason || undefined,
          adjustment_detail: adjustmentDetail || undefined,
          purchase_store: purchaseStore || undefined,
          purchase_price: purchasePrice !== '' ? parseFloat(purchasePrice) : undefined
        })
      })
      const data = await res.json()
      if (data.success) {
        
        setFlash(skip ? 'skip' : 'success')
        setTimeout(() => {
          setFlash(null)
          setCurrentItem(data.next)
          setProgress(data.progress)
          setActualQty('')
          setItemNotes('')
          setMinStock(data.next?.min_stock != null ? String(data.next.min_stock) : '')
          setAdjustmentReason('')
          setAdjustmentDetail('')
          setPurchaseStore('')
          setPurchasePrice('')
          setShowNotes(false)
          if (!data.next) showToast('Semua item sudah dicek!', 'success')
          
          if (itemListData.length > 0) fetchItemList()
        }, 300)
      } else showToast(data.message, 'error')
    } catch (e) { showToast('Gagal menyimpan', 'error') }
    finally { setSubmitting(false) }
  }

  const handleKeyDown = (e) => {
    if (e.key === 'Enter' && !submitting && actualQty !== '') {
      e.preventDefault()
      handleSubmit()
    }
  }

  const handlePrev = async () => {
    setSubmitting(true)
    try {
      const res = await authFetch(`${API_BASE}/stock-check/${session.id}/prev${sortBy !== 'default' ? `?sort=${sortBy}` : ''}`)
      const data = await res.json()
      if (data.success) {
        setCurrentItem(data.data)
        setProgress(data.progress)
        setActualQty('')
        setItemNotes('')
        setMinStock(data.data?.min_stock != null ? String(data.data.min_stock) : '')
        setAdjustmentReason('')
        setAdjustmentDetail('')
        setPurchaseStore('')
        setPurchasePrice('')
        setShowNotes(false)
      } else {
        showToast(data.message || 'Tidak ada item sebelumnya', 'error')
      }
    } catch (e) { showToast('Gagal kembali', 'error') }
    finally { setSubmitting(false) }
  }

  const pct = progress.total > 0 ? Math.round((progress.checked / progress.total) * 100) : 0
  const diff = actualQty !== '' && !isNaN(parseFloat(actualQty)) ? parseFloat(actualQty) - parseFloat(currentItem?.recorded_qty || 0) : null

  return (
    <div className='flex flex-col h-[calc(100vh-8rem)] sm:h-auto sm:max-w-lg sm:mx-auto'>
      { }
      <div className='bg-white rounded-xl border border-slate-200 p-3 mb-3 shrink-0'>
        <div className='flex items-center gap-2 mb-2'>
          <button onClick={onBack} className='p-1.5 hover:bg-slate-100 rounded-lg transition active:bg-slate-200 shrink-0'><FiArrowLeft size={18} /></button>
          <div className='flex-1 min-w-0'>
            <p className='text-sm font-bold text-slate-800 truncate'>{session.check_name}</p>
          </div>
          <button onClick={() => setShowItemList(!showItemList)}
            className={`p-1.5 rounded-lg transition shrink-0 ${showItemList ? 'bg-blue-100 text-blue-700' : 'hover:bg-slate-100 text-slate-500'}`}
            title='Daftar Item'>
            <FiList size={18} />
          </button>
          <select value={sortBy} onChange={e => setSortBy(e.target.value)}
            className='text-[10px] px-2 py-1 border border-slate-300 rounded-lg bg-white text-slate-600 focus:outline-none shrink-0'>
            <option value='default'>Urut: Default</option>
            <option value='name'>Urut: Nama A-Z</option>
            <option value='category'>Urut: Kategori</option>
          </select>
          <span className='text-xs font-semibold text-blue-600 bg-blue-50 px-2 py-1 rounded-full shrink-0'>{progress.checked}/{progress.total}</span>
        </div>
        <div className='w-full h-2 bg-slate-100 rounded-full overflow-hidden'>
          <div className='h-full bg-gradient-to-r from-blue-500 to-blue-400 rounded-full transition-all duration-500 ease-out' style={{ width: `${pct}%` }} />
        </div>
      </div>

      { }
      {showItemList && (
        <div className='bg-white rounded-xl border border-slate-200 mb-3 overflow-hidden shrink-0' style={{ maxHeight: 'calc(100vh - 14rem)' }}>
          { }
          <div className='p-3 border-b border-slate-100 space-y-2'>
            <div className='relative'>
              <FiSearch className='absolute left-3 top-1/2 -translate-y-1/2 text-slate-400' size={14} />
              <input ref={searchInputRef} type='text' placeholder='Cari nama atau kode material...' value={itemListSearch}
                onChange={e => setItemListSearch(e.target.value)} autoFocus
                className='w-full pl-9 pr-8 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500' />
              {itemListSearch && (
                <button onClick={() => setItemListSearch('')} className='absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600'>
                  <FiX size={14} />
                </button>
              )}
            </div>
            <div className='flex gap-1.5 flex-wrap'>
              {[
                { key: 'all', label: 'Semua', count: itemListData.length },
                { key: 'pending', label: 'Belum', color: 'amber', count: itemListData.filter(i => i.status === 'pending').length },
                { key: 'checked', label: 'Sudah', color: 'emerald', count: itemListData.filter(i => i.status === 'checked').length },
                { key: 'skipped', label: 'Dilewati', color: 'slate', count: itemListData.filter(i => i.status === 'skipped').length },
              ].map(f => (
                <button key={f.key} onClick={() => setItemListFilter(f.key)}
                  className={`px-2.5 py-1 text-[11px] rounded-lg border transition font-medium ${itemListFilter === f.key
                    ? f.color === 'amber' ? 'bg-amber-50 border-amber-300 text-amber-700'
                    : f.color === 'emerald' ? 'bg-emerald-50 border-emerald-300 text-emerald-700'
                    : f.color === 'slate' ? 'bg-slate-100 border-slate-400 text-slate-700'
                    : 'bg-blue-50 border-blue-300 text-blue-700'
                    : 'border-slate-200 text-slate-500 hover:bg-slate-50'}`}>
                  {f.label} ({f.count})
                </button>
              ))}
            </div>
          </div>
          { }
          <div className='overflow-y-auto' style={{ maxHeight: 'calc(100vh - 22rem)' }}>
            {itemListLoading ? (
              <div className='p-6 text-center text-slate-400'>
                <div className='w-5 h-5 border-2 border-slate-300 border-t-slate-600 rounded-full animate-spin mx-auto mb-2' />Memuat...
              </div>
            ) : filteredItemList.length === 0 ? (
              <div className='p-6 text-center text-slate-400 text-sm'>Tidak ada item ditemukan</div>
            ) : filteredItemList.map(item => {
              const isCurrent = currentItem && currentItem.id === item.id
              const hasDiff = item.status === 'checked' && item.difference !== null && parseFloat(item.difference) !== 0
              return (
                <button key={item.id} onClick={() => handleJumpToItem(item.id)}
                  className={`w-full text-left px-3 py-2.5 border-b border-slate-50 transition flex items-center gap-2.5 ${
                    isCurrent ? 'bg-blue-50' : 'hover:bg-slate-50 active:bg-slate-100 cursor-pointer'}`}>
                  { }
                  <div className='shrink-0'>
                    {item.status === 'checked' ? (
                      hasDiff ? <FiAlertTriangle size={14} className='text-red-500' /> : <FiCheckCircle size={14} className='text-emerald-500' />
                    ) : item.status === 'skipped' ? (
                      <FiSkipForward size={14} className='text-slate-400' />
                    ) : (
                      <div className='w-3.5 h-3.5 rounded-full border-2 border-amber-400' />
                    )}
                  </div>
                  { }
                  <div className='flex-1 min-w-0'>
                    <div className='flex items-center gap-1.5'>
                      <span className='text-[10px] text-slate-400 font-mono'>#{item.sort_order}</span>
                      <span className='text-xs font-medium text-slate-800 truncate'>{item.material_name}</span>
                    </div>
                    <div className='flex items-center gap-2 mt-0.5'>
                      {item.material_code && <span className='text-[10px] text-slate-400 font-mono'>{item.material_code}</span>}
                      {item.category && <span className='text-[10px] px-1.5 py-0 bg-slate-100 text-slate-500 rounded'>{item.category}</span>}
                    </div>
                  </div>
                  { }
                  <div className='shrink-0 text-right'>
                    <div className='text-xs font-semibold text-slate-700'>{formatNumber(item.recorded_qty)}</div>
                    {item.status === 'checked' && item.actual_qty !== null && (
                      <div className={`text-[10px] font-medium ${hasDiff ? 'text-red-600' : 'text-emerald-600'}`}>
                        → {formatNumber(item.actual_qty)}
                      </div>
                    )}
                  </div>
                </button>
              )
            })}
          </div>
        </div>
      )}

      { }
      <div className='flex-1 min-h-0 flex flex-col'>
        {loading ? (
          <div className='flex-1 flex items-center justify-center'>
            <div className='text-center'>
              <div className='w-10 h-10 border-3 border-slate-300 border-t-blue-600 rounded-full animate-spin mx-auto mb-3' />
              <p className='text-slate-400 text-sm'>Memuat item...</p>
            </div>
          </div>
        ) : !currentItem ? (
          <div className='flex-1 flex items-center justify-center'>
            <div className='text-center px-6'>
              <div className='w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-4'>
                <FiCheckCircle className='text-emerald-500' size={32} />
              </div>
              <h2 className='text-xl font-bold text-slate-800 mb-1'>Semua Selesai!</h2>
              <p className='text-sm text-slate-500 mb-6'>Semua {progress.total} item sudah dicek.</p>
              <button onClick={onBack} className='w-full px-6 py-3 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700 active:bg-blue-800 transition'>
                Kembali ke Daftar
              </button>
            </div>
          </div>
        ) : (
          <div className={`flex-1 flex flex-col bg-white rounded-xl border border-slate-200 overflow-hidden transition-all duration-300 ${flash === 'success' ? 'ring-2 ring-emerald-400 bg-emerald-50' : flash === 'skip' ? 'opacity-50' : ''}`}>
            { }
            <div className='flex-1 overflow-y-auto p-4 space-y-3'>
              { }
              <div className='flex items-center justify-between'>
                <span className='text-xs font-medium text-slate-400'>#{currentItem.sort_order} dari {progress.total}</span>
                {currentItem.category && <span className='px-2 py-0.5 bg-slate-100 text-slate-500 rounded text-[10px] font-medium'>{currentItem.category}</span>}
              </div>

              { }
              <div>
                <h2 className='text-base sm:text-lg font-bold text-slate-800 leading-tight'>{currentItem.material_name}</h2>
                {currentItem.material_code && <p className='text-xs text-slate-400 font-mono mt-0.5'>{currentItem.material_code}</p>}
                {currentItem.description && <p className='text-xs text-slate-500 mt-1'>{currentItem.description}</p>}
              </div>

              { }
              <div className='bg-blue-50 rounded-xl p-4 text-center'>
                <p className='text-xs text-blue-600 font-medium mb-1'>Jumlah Tercatat</p>
                <p className='text-3xl sm:text-4xl font-black text-blue-700'>{formatNumber(currentItem.recorded_qty)}</p>
                <p className='text-xs text-blue-500 mt-1'>{currentItem.unit || 'pcs'}</p>
              </div>

              { }
              <div>
                <label className='block text-xs font-medium text-slate-600 mb-1.5'>Jumlah Sebenarnya</label>
                <input ref={inputRef} type='number' inputMode='decimal' value={actualQty} onChange={e => setActualQty(e.target.value)} onKeyDown={handleKeyDown}
                  placeholder='0' min={0}
                  className='w-full px-4 py-4 border-2 border-slate-300 rounded-xl text-2xl font-bold text-center focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition' />
              </div>

              { }
              {diff !== null && (
                <div className={`rounded-xl p-3 text-center font-medium ${
                  diff === 0 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' :
                  diff < 0 ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-amber-50 text-amber-700 border border-amber-200'}`}>
                  {diff === 0
                    ? <span className='flex items-center justify-center gap-1.5'><FiCheckCircle size={16} /> Sesuai</span>
                    : <span>Selisih: <strong>{diff > 0 ? '+' : ''}{formatNumber(diff)}</strong> {currentItem.unit || ''}</span>}
                </div>
              )}

              { }
              {diff !== null && diff > 0 && (
                <div className='space-y-2'>
                  <div>
                    <label className='block text-xs font-medium text-slate-600 mb-1.5'>Alasan Penambahan</label>
                    <select value={adjustmentReason} onChange={e => { setAdjustmentReason(e.target.value); setAdjustmentDetail(''); setPurchaseStore(''); setPurchasePrice('') }}
                      className='w-full px-3 py-2.5 border border-slate-300 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500'>
                      <option value=''>-- Pilih alasan --</option>
                      <option value='pembelian'>Pembelian</option>
                      <option value='retur'>Retur dari proyek</option>
                      <option value='transfer'>Transfer dari gudang lain</option>
                      <option value='koreksi'>Koreksi data</option>
                      <option value='lainnya'>Lainnya</option>
                    </select>
                  </div>
                  {adjustmentReason === 'pembelian' && (
                    <div className='bg-blue-50 rounded-xl p-3 space-y-2 border border-blue-200'>
                      <select value={purchaseStore} onChange={e => setPurchaseStore(e.target.value)}
                        className='w-full px-3 py-2 border border-blue-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500'>
                        <option value=''>-- Pilih supplier --</option>
                        {suppliers.map(s => <option key={s.id} value={s.name}>{s.name}</option>)}
                      </select>
                      <input type='number' inputMode='decimal' value={purchasePrice} onChange={e => setPurchasePrice(e.target.value)}
                        placeholder='Harga satuan (Rp)' min={0} className='w-full px-3 py-2 border border-blue-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500' />
                      {purchasePrice && diff > 0 && (
                        <div className='text-xs text-blue-700 bg-blue-100 rounded-lg p-2'>
                          <p>Total beli: <strong>Rp {(diff * parseFloat(purchasePrice)).toLocaleString('id-ID')}</strong></p>
                          <p>Avg baru: <strong>Rp {(((parseFloat(currentItem.recorded_qty) * parseFloat(currentItem.avg_price || 0)) + (diff * parseFloat(purchasePrice))) / parseFloat(actualQty)).toLocaleString('id-ID', {maximumFractionDigits: 0})}</strong></p>
                        </div>
                      )}
                    </div>
                  )}
                  {adjustmentReason === 'retur' && (
                    <input type='text' value={adjustmentDetail} onChange={e => setAdjustmentDetail(e.target.value)}
                      placeholder='Nama proyek retur' className='w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500' />
                  )}
                  {adjustmentReason === 'transfer' && (
                    <input type='text' value={adjustmentDetail} onChange={e => setAdjustmentDetail(e.target.value)}
                      placeholder='Asal gudang' className='w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500' />
                  )}
                  {adjustmentReason === 'koreksi' && (
                    <input type='text' value={adjustmentDetail} onChange={e => setAdjustmentDetail(e.target.value)}
                      placeholder='Alasan koreksi...' className='w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500' />
                  )}
                  {adjustmentReason === 'lainnya' && (
                    <input type='text' value={adjustmentDetail} onChange={e => setAdjustmentDetail(e.target.value)}
                      placeholder='Jelaskan...' className='w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500' />
                  )}
                </div>
              )}

              { }
              {diff !== null && diff < 0 && (
                <div className='space-y-2'>
                  <div>
                    <label className='block text-xs font-medium text-slate-600 mb-1.5'>Alasan Pengurangan</label>
                    <select value={adjustmentReason} onChange={e => { setAdjustmentReason(e.target.value); setAdjustmentDetail('') }}
                      className='w-full px-3 py-2.5 border border-slate-300 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500'>
                      <option value=''>-- Pilih alasan --</option>
                      <option value='terpakai'>Terpakai di proyek</option>
                      <option value='rusak'>Rusak / expired</option>
                      <option value='hilang'>Hilang</option>
                      <option value='koreksi'>Koreksi data</option>
                      <option value='lainnya'>Lainnya</option>
                    </select>
                  </div>
                  {adjustmentReason === 'terpakai' && (
                    <input type='text' value={adjustmentDetail} onChange={e => setAdjustmentDetail(e.target.value)}
                      placeholder='Nama proyek' className='w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500' />
                  )}
                  {adjustmentReason === 'rusak' && (
                    <input type='text' value={adjustmentDetail} onChange={e => setAdjustmentDetail(e.target.value)}
                      placeholder='Keterangan kerusakan...' className='w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500' />
                  )}
                  {adjustmentReason === 'hilang' && (
                    <input type='text' value={adjustmentDetail} onChange={e => setAdjustmentDetail(e.target.value)}
                      placeholder='Keterangan...' className='w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500' />
                  )}
                  {adjustmentReason === 'koreksi' && (
                    <input type='text' value={adjustmentDetail} onChange={e => setAdjustmentDetail(e.target.value)}
                      placeholder='Alasan koreksi...' className='w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500' />
                  )}
                  {adjustmentReason === 'lainnya' && (
                    <input type='text' value={adjustmentDetail} onChange={e => setAdjustmentDetail(e.target.value)}
                      placeholder='Jelaskan...' className='w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500' />
                  )}
                </div>
              )}

              { }
              <div>
                <label className='block text-xs font-medium text-slate-600 mb-1.5'>Stok Minimum</label>
                <input type='number' inputMode='decimal' value={minStock} onChange={e => setMinStock(e.target.value)}
                  placeholder='0' min={0}
                  className='w-full px-3 py-2.5 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition' />
              </div>

              { }
              {!showNotes ? (
                <button onClick={() => setShowNotes(true)} className='text-xs text-slate-400 hover:text-slate-600 transition'>
                  + Tambah catatan
                </button>
              ) : (
                <input type='text' value={itemNotes} onChange={e => setItemNotes(e.target.value)} autoFocus
                  placeholder='Catatan opsional...' className='w-full px-3 py-2.5 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500' />
              )}
            </div>

            { }
            <div className='shrink-0 p-3 bg-slate-50 border-t border-slate-200 safe-bottom'>
              <div className='flex gap-2 mb-2'>
                <button onClick={handlePrev} disabled={submitting || progress.checked === 0}
                  className='flex items-center gap-1 px-3 py-2 text-xs text-slate-500 bg-white border border-slate-300 rounded-xl hover:bg-slate-100 active:bg-slate-200 transition disabled:opacity-30 font-medium'>
                  <FiArrowLeft size={14} /> Kembali
                </button>
                <button onClick={() => handleSubmit(false, true)} disabled={submitting}
                  className='flex-1 flex items-center justify-center gap-1 py-2 text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-300 rounded-xl hover:bg-emerald-100 active:bg-emerald-200 transition disabled:opacity-40'>
                  <FiCheckCircle size={14} /> Stok Sama ({formatNumber(currentItem.recorded_qty)})
                </button>
              </div>
              <div className='flex gap-2'>
                <button onClick={() => handleSubmit(true)} disabled={submitting}
                  className='px-4 py-3.5 text-sm text-slate-500 bg-white border border-slate-300 rounded-xl hover:bg-slate-100 active:bg-slate-200 transition disabled:opacity-50 font-medium'>
                  <FiSkipForward size={16} className='mx-auto' />
                </button>
                <button onClick={() => handleSubmit(false)} disabled={submitting || actualQty === ''}
                  className='flex-1 flex items-center justify-center gap-2 py-3.5 bg-blue-600 text-white text-sm font-semibold rounded-xl hover:bg-blue-700 active:bg-blue-800 disabled:opacity-40 transition'>
                  {submitting ? (
                    <div className='w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin' />
                  ) : (
                    <><FiCheck size={18} /> Simpan & Lanjut</>
                  )}
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  )
}



function ReportView({ authFetch, showToast, session, isAdmin, onBack }) {
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [filter, setFilter] = useState('all') 
  const [page, setPage] = useState(1)
  const [completing, setCompleting] = useState(false)

  const fetchReport = useCallback(async () => {
    setLoading(true)
    try {
      const params = new URLSearchParams({ page, limit: 50 })
      if (search) params.set('search', search)
      if (filter === 'discrepancy') params.set('status', 'checked')
      if (filter === 'pending') params.set('status', 'pending')
      const res = await authFetch(`${API_BASE}/stock-check/${session.id}?${params}`)
      const d = await res.json()
      if (d.success) setData(d.data)
    } catch (e) { showToast('Gagal memuat laporan', 'error') }
    finally { setLoading(false) }
  }, [authFetch, session.id, search, filter, page])

  useEffect(() => { fetchReport() }, [fetchReport])

  const handleComplete = async (applyAdjustments) => {
    const msg = applyAdjustments
      ? 'Selesaikan pengecekan DAN sesuaikan stok di sistem berdasarkan hasil opname?'
      : 'Selesaikan pengecekan TANPA mengubah stok?'
    if (!confirm(msg)) return
    setCompleting(true)
    try {
      const res = await authFetch(`${API_BASE}/stock-check/${session.id}/complete`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ apply_adjustments: applyAdjustments })
      })
      const d = await res.json()
      if (d.success) { showToast(d.message, 'success'); onBack() }
      else showToast(d.message, 'error')
    } catch (e) { showToast('Gagal menyelesaikan', 'error') }
    finally { setCompleting(false) }
  }

  const handleExportReport = async () => {
    if (!data || !data.items) return
    
    try {
      const res = await authFetch(`${API_BASE}/stock-check/${session.id}?limit=10000`)
      const d = await res.json()
      if (!d.success) return showToast('Gagal export', 'error')

      const allItems = d.data.items
      const wb = new ExcelJS.Workbook()
      const ws = wb.addWorksheet('Laporan Stock Opname')

      
      ws.mergeCells('A1:H1')
      ws.getCell('A1').value = `Laporan Stock Opname: ${data.check_name}`
      ws.getCell('A1').font = { bold: true, size: 14 }
      ws.mergeCells('A2:H2')
      ws.getCell('A2').value = `Tanggal: ${new Date(data.check_date).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })} | Status: ${data.status === 'completed' ? 'Selesai' : 'Berjalan'}`
      ws.getCell('A2').font = { size: 10, color: { argb: 'FF666666' } }

      ws.addRow([])

      
      const headerRow = ws.addRow(['No', 'Kode', 'Nama Material', 'Satuan', 'Jumlah Tercatat', 'Jumlah Sebenarnya', 'Selisih', 'Stok Minimum', 'Status', 'Alasan', 'Detail', 'Toko/Supplier', 'Harga Satuan', 'Catatan'])
      headerRow.eachCell(cell => {
        cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF1E293B' } }
        cell.font = { bold: true, color: { argb: 'FFFFFFFF' }, size: 10 }
        cell.alignment = { horizontal: 'center' }
      })

      ws.columns = [
        { width: 6 }, { width: 16 }, { width: 35 }, { width: 10 },
        { width: 16 }, { width: 18 }, { width: 12 }, { width: 14 }, { width: 12 }, { width: 14 }, { width: 20 }, { width: 20 }, { width: 16 }, { width: 25 }
      ]

      allItems.forEach((item, i) => {
        const row = ws.addRow([
          i + 1,
          item.material_code || '-',
          item.material_name,
          item.unit || '-',
          parseFloat(item.recorded_qty) || 0,
          item.actual_qty !== null ? parseFloat(item.actual_qty) : '-',
          item.difference !== null ? parseFloat(item.difference) : '-',
          item.min_stock != null ? parseFloat(item.min_stock) : '-',
          item.status === 'checked' ? 'Dicek' : item.status === 'skipped' ? 'Dilewati' : 'Pending',
          item.adjustment_reason || '',
          item.adjustment_detail || '',
          item.purchase_store || '',
          item.purchase_price != null ? parseFloat(item.purchase_price) : '',
          item.notes || ''
        ])

        
        if (item.status === 'checked' && item.difference !== null && parseFloat(item.difference) !== 0) {
          const diffCell = row.getCell(7)
          diffCell.font = { color: { argb: parseFloat(item.difference) < 0 ? 'FFDC2626' : 'FFD97706' }, bold: true }
          row.getCell(3).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFFF1F2' } }
        }
      })

      const buf = await wb.xlsx.writeBuffer()
      saveAs(new Blob([buf]), `opname_${data.check_name.replace(/[^a-zA-Z0-9]/g, '_')}_${new Date().toISOString().slice(0, 10)}.xlsx`)
      showToast('Laporan berhasil diexport', 'success')
    } catch (e) { showToast('Gagal export', 'error') }
  }

  const filteredItems = data?.items?.filter(i => {
    if (filter === 'discrepancy') return i.status === 'checked' && i.difference !== null && parseFloat(i.difference) !== 0
    if (filter === 'ok') return i.status === 'checked' && (i.difference === null || parseFloat(i.difference) === 0)
    if (filter === 'pending') return i.status === 'pending'
    return true
  }) || []

  const pct = data ? (data.total_items > 0 ? Math.round((data.checked_items / data.total_items) * 100) : 0) : 0

  return (
    <div className='space-y-4'>
      { }
      <div className='flex items-center gap-3'>
        <button onClick={onBack} className='p-2 hover:bg-slate-100 rounded-lg transition shrink-0'><FiArrowLeft size={20} /></button>
        <div className='flex-1 min-w-0'>
          <h1 className='text-base sm:text-lg font-bold text-slate-800 truncate'>{session.check_name}</h1>
          <p className='text-xs sm:text-sm text-slate-500'>
            {data ? new Date(data.check_date).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) : ''}
            {data?.status === 'completed' && <span className='ml-2 px-2 py-0.5 bg-emerald-50 text-emerald-600 rounded text-xs font-medium'>Selesai</span>}
          </p>
        </div>
      </div>

      { }
      <div className='flex flex-wrap gap-2'>
        <button onClick={handleExportReport} className='flex items-center gap-1.5 px-3 py-2 text-sm border border-slate-300 rounded-lg text-slate-600 hover:bg-slate-50 active:bg-slate-100 transition'>
          <FiDownload size={14} /> Export
        </button>
        {isAdmin && data?.status !== 'completed' && (
          <>
            <button onClick={() => handleComplete(false)} disabled={completing}
              className='flex items-center gap-1.5 px-3 py-2 text-sm border border-slate-300 rounded-lg text-slate-600 hover:bg-slate-50 active:bg-slate-100 disabled:opacity-50 transition'>
              <FiCheck size={14} /> Selesaikan
            </button>
            <button onClick={() => handleComplete(true)} disabled={completing}
              className='flex items-center gap-1.5 px-3 py-2 text-sm bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 active:bg-emerald-800 disabled:opacity-50 transition'>
              <FiCheckCircle size={14} /> Selesai & Sesuaikan Stok
            </button>
          </>
        )}
      </div>

      { }
      {data && (
        <div className='grid grid-cols-2 md:grid-cols-4 gap-3'>
          <div className='bg-white rounded-xl p-4 border border-slate-200'>
            <p className='text-xs text-slate-500 mb-1'>Total Item</p>
            <p className='text-2xl font-bold text-slate-800'>{formatNumber(data.total_items)}</p>
          </div>
          <div className='bg-white rounded-xl p-4 border border-slate-200'>
            <p className='text-xs text-slate-500 mb-1'>Sudah Dicek</p>
            <p className='text-2xl font-bold text-blue-600'>{formatNumber(data.checked_items)} <span className='text-sm text-slate-400'>({pct}%)</span></p>
          </div>
          <div className={`bg-white rounded-xl p-4 border ${data.discrepancy_count > 0 ? 'border-red-300' : 'border-slate-200'}`}>
            <p className='text-xs text-slate-500 mb-1'>Selisih Ditemukan</p>
            <p className={`text-2xl font-bold ${data.discrepancy_count > 0 ? 'text-red-600' : 'text-emerald-600'}`}>{formatNumber(data.discrepancy_count)}</p>
          </div>
          <div className='bg-white rounded-xl p-4 border border-slate-200'>
            <p className='text-xs text-slate-500 mb-1'>Belum Dicek</p>
            <p className='text-2xl font-bold text-amber-600'>{formatNumber(data.total_items - data.checked_items)}</p>
          </div>
        </div>
      )}

      { }
      <div className='bg-white rounded-xl p-4 border border-slate-200'>
        <div className='flex flex-col sm:flex-row gap-3'>
          <div className='relative flex-1'>
            <FiSearch className='absolute left-3 top-1/2 -translate-y-1/2 text-slate-400' size={16} />
            <input type='text' placeholder='Cari nama atau kode...' value={search} onChange={e => { setSearch(e.target.value); setPage(1) }}
              className='w-full pl-9 pr-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500' />
          </div>
          <div className='flex gap-1.5'>
            {[
              { key: 'all', label: 'Semua' },
              { key: 'discrepancy', label: 'Selisih', color: 'red' },
              { key: 'ok', label: 'Sesuai', color: 'emerald' },
              { key: 'pending', label: 'Pending', color: 'amber' },
            ].map(f => (
              <button key={f.key} onClick={() => { setFilter(f.key); setPage(1) }}
                className={`px-3 py-2 text-xs rounded-lg border transition font-medium ${filter === f.key
                  ? f.color === 'red' ? 'bg-red-50 border-red-300 text-red-700'
                  : f.color === 'emerald' ? 'bg-emerald-50 border-emerald-300 text-emerald-700'
                  : f.color === 'amber' ? 'bg-amber-50 border-amber-300 text-amber-700'
                  : 'bg-blue-50 border-blue-300 text-blue-700'
                  : 'border-slate-300 text-slate-600 hover:bg-slate-50'}`}>
                {f.label}
              </button>
            ))}
          </div>
        </div>
      </div>

      { }
      <div className='bg-white rounded-xl border border-slate-200 overflow-hidden'>
        <div className='overflow-x-auto'>
          <table className='w-full text-sm'>
            <thead>
              <tr className='bg-slate-50 text-slate-600 text-left'>
                <th className='px-4 py-3 font-semibold w-10'>#</th>
                <th className='px-4 py-3 font-semibold'>Kode</th>
                <th className='px-4 py-3 font-semibold'>Nama Material</th>
                <th className='px-4 py-3 font-semibold text-center'>Satuan</th>
                <th className='px-4 py-3 font-semibold text-right'>Tercatat</th>
                <th className='px-4 py-3 font-semibold text-right'>Sebenarnya</th>
                <th className='px-4 py-3 font-semibold text-right'>Selisih</th>
                <th className='px-4 py-3 font-semibold text-right'>Stok Min</th>
                <th className='px-4 py-3 font-semibold text-center'>Status</th>
                <th className='px-4 py-3 font-semibold'>Alasan</th>
                <th className='px-4 py-3 font-semibold'>Catatan</th>
              </tr>
            </thead>
            <tbody className='divide-y divide-slate-100'>
              {loading ? (
                <tr><td colSpan={11} className='px-4 py-12 text-center text-slate-400'>
                  <div className='w-6 h-6 border-2 border-slate-300 border-t-slate-600 rounded-full animate-spin mx-auto mb-2' />Memuat...</td></tr>
              ) : filteredItems.length === 0 ? (
                <tr><td colSpan={11} className='px-4 py-12 text-center text-slate-400'>Tidak ada data</td></tr>
              ) : filteredItems.map((item) => {
                const hasDiff = item.status === 'checked' && item.difference !== null && parseFloat(item.difference) !== 0
                return (
                  <tr key={item.id} className={`hover:bg-slate-50 transition ${hasDiff ? 'bg-red-50/50' : ''}`}>
                    <td className='px-4 py-3 text-slate-400 text-xs'>{item.sort_order}</td>
                    <td className='px-4 py-3 font-mono text-xs text-slate-500'>{item.material_code || '-'}</td>
                    <td className='px-4 py-3 font-medium text-slate-800'>
                      {item.material_name}
                      {item.category && <span className='ml-2 px-1.5 py-0.5 bg-slate-100 text-slate-500 rounded text-[10px]'>{item.category}</span>}
                    </td>
                    <td className='px-4 py-3 text-center text-slate-500'>{item.unit || '-'}</td>
                    <td className='px-4 py-3 text-right font-semibold text-slate-700'>{formatNumber(item.recorded_qty)}</td>
                    <td className='px-4 py-3 text-right font-semibold'>
                      {item.actual_qty !== null ? (
                        <span className={hasDiff ? 'text-red-600' : 'text-emerald-600'}>{formatNumber(item.actual_qty)}</span>
                      ) : <span className='text-slate-300'>-</span>}
                    </td>
                    <td className='px-4 py-3 text-right'>
                      {item.difference !== null ? (
                        <span className={`font-semibold ${parseFloat(item.difference) === 0 ? 'text-emerald-600' : parseFloat(item.difference) < 0 ? 'text-red-600' : 'text-amber-600'}`}>
                          {parseFloat(item.difference) > 0 ? '+' : ''}{formatNumber(item.difference)}
                        </span>
                      ) : <span className='text-slate-300'>-</span>}
                    </td>
                    <td className='px-4 py-3 text-center'>
                      <span className={`inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full text-[10px] font-medium ${
                        item.status === 'checked' ? (hasDiff ? 'bg-red-100 text-red-600' : 'bg-emerald-100 text-emerald-600')
                        : item.status === 'skipped' ? 'bg-slate-100 text-slate-500' : 'bg-amber-100 text-amber-600'}`}>
                        {item.status === 'checked' ? (hasDiff ? <><FiAlertTriangle size={8} /> Selisih</> : <><FiCheckCircle size={8} /> OK</>)
                         : item.status === 'skipped' ? 'Dilewati' : 'Pending'}
                      </span>
                    </td>
                    <td className='px-4 py-3 text-right text-slate-600'>{item.min_stock != null ? formatNumber(item.min_stock) : <span className='text-slate-300'>-</span>}</td>
                    <td className='px-4 py-3 text-center'>
                      {item.adjustment_reason ? (
                        <div className='text-left'>
                          <span className='px-1.5 py-0.5 bg-slate-100 text-slate-600 rounded text-[10px] font-medium capitalize'>{item.adjustment_reason}</span>
                          {item.purchase_store && <p className='text-[10px] text-slate-500 mt-0.5'>Toko: {item.purchase_store}</p>}
                          {item.purchase_price != null && <p className='text-[10px] text-slate-500'>@ Rp {parseFloat(item.purchase_price).toLocaleString('id-ID')}</p>}
                          {item.adjustment_detail && <p className='text-[10px] text-slate-400 mt-0.5'>{item.adjustment_detail}</p>}
                        </div>
                      ) : <span className='text-slate-300'>-</span>}
                    </td>
                    <td className='px-4 py-3 text-xs text-slate-400 truncate max-w-[150px]'>{item.notes || '-'}</td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
        {data?.pagination?.totalPages > 1 && (
          <div className='flex items-center justify-between px-4 py-3 border-t border-slate-100'>
            <p className='text-xs text-slate-500'>Hal {page} dari {data.pagination.totalPages}</p>
            <div className='flex gap-1'>
              <button disabled={page <= 1} onClick={() => setPage(p => p - 1)} className='p-1.5 rounded border border-slate-300 disabled:opacity-30 hover:bg-slate-50'><FiChevronLeft size={16} /></button>
              <button disabled={page >= data.pagination.totalPages} onClick={() => setPage(p => p + 1)} className='p-1.5 rounded border border-slate-300 disabled:opacity-30 hover:bg-slate-50'><FiChevronRight size={16} /></button>
            </div>
          </div>
        )}
      </div>
    </div>
  )
}
