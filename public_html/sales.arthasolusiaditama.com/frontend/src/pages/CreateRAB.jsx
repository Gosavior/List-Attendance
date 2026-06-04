import React, { useState, useEffect, useMemo } from 'react'
import { FiPlus, FiTrash2, FiArrowLeft, FiSave, FiFileText, FiPackage, FiArchive, FiUsers, FiAlertCircle, FiShoppingCart, FiCheck, FiUpload, FiImage } from 'react-icons/fi'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'

const API_BASE = import.meta.env.VITE_API_BASE || '/api'

const emptyRow = () => ({ item: '', qty: '', unit: '', price: '', store: '' })
const emptyRowB = () => ({ item: '', qtyNeeded: '', qtyAvailable: '', unit: '', price: '', store: '', receiptUrl: '' })

const formatRupiah = (num) => {
  if (!num || isNaN(num)) return 'Rp 0'
  return 'Rp ' + Number(num).toLocaleString('id-ID')
}

const formatThousand = (val) => {
  if (!val && val !== 0) return ''
  return Number(val).toLocaleString('id-ID')
}

const generateRandomRab = () => {
  const rand = Math.floor(100000 + Math.random() * 900000)
  return `RAB-BAY-${rand}`
}

const parseThousand = (str) => {
  if (!str) return ''
  const cleaned = str.replace(/\./g, '').replace(/[^0-9]/g, '')
  return cleaned === '' ? '' : cleaned
}

const CreateRAB = () => {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const { authFetch } = useAuth()
  const projectId = searchParams.get('project')
  const editRabId = searchParams.get('rab')
  const rabType = searchParams.get('type') || 'nyata'

  const [projectName, setProjectName] = useState('')
  const [projectAoNumber, setProjectAoNumber] = useState('') 
  const [createDate, setCreateDate] = useState(new Date().toISOString().split('T')[0])
  const [saving, setSaving] = useState(false)
  const [saveError, setSaveError] = useState('')
  const [saved, setSaved] = useState(false)
  const [existingRabId, setExistingRabId] = useState(null)
  const [rabNumber, setRabNumber] = useState('')
  const [loadingData, setLoadingData] = useState(false)

  const [sectionA, setSectionA] = useState([emptyRow()])
  const [sectionB, setSectionB] = useState([emptyRowB()])
  const [sectionC, setSectionC] = useState([emptyRow()])

  const [sectionD, setSectionD] = useState([emptyRow()])
  const [mrItemsLoaded, setMrItemsLoaded] = useState(false)
  const [mrItemCount, setMrItemCount] = useState(0)

  useEffect(() => {
    if (!projectId) return
    const loadProject = async () => {
      try {
        const res = await authFetch(`${API_BASE}/projects/${projectId}`)
        const data = await res.json()
        if (data.success) {
          setProjectName(data.data.project_name)
          if (data.data.ao_number) setProjectAoNumber(data.data.ao_number)
          if (rabType === 'nyata' && !existingRabId) {
            if (data.data.ao_number) setRabNumber(data.data.ao_number.replace(/^AO-/i, 'RAB-'))
          }
        }
      } catch (err) {
        console.error('Load project error:', err)
      }
    }
    loadProject()
  }, [projectId, authFetch, rabType, existingRabId])

  useEffect(() => {
    const rabToLoad = editRabId || null
    if (!rabToLoad && !projectId) return
    const loadRAB = async () => {
      setLoadingData(true)
      let savedSectionC = []
      try {
        const url = rabToLoad
          ? `${API_BASE}/rab/${rabToLoad}`
          : `${API_BASE}/rab/project/${projectId}${rabType !== 'nyata' ? '?type=' + rabType : ''}`
        const res = await authFetch(url)
        const data = await res.json()
        if (data.success && data.data) {
          const rab = data.data
          setExistingRabId(rab.id)
          if (rab.rab_number) setRabNumber(rab.rab_number)
          if (rab.sectionA?.length) setSectionA(rab.sectionA.map(r => ({ item: r.item, qty: String(r.qty || ''), unit: r.unit || '', price: String(r.price || ''), store: r.store || '', receiptUrl: r.receipt || '' })))
          if (rab.sectionB?.length) setSectionB(rab.sectionB.map(r => ({ item: r.item, qtyNeeded: String(r.qtyNeeded || ''), qtyAvailable: String(r.qtyAvailable || ''), unit: r.unit || '', price: String(r.price || ''), store: r.store || '', receiptUrl: r.receipt || '' })))
          if (rab.sectionC?.length) {
            savedSectionC = rab.sectionC.map(r => ({ item: r.item, qty: String(r.qty || ''), unit: r.unit || '', price: String(r.price || '') }))
          }
          if (rab.sectionD?.length) setSectionD(rab.sectionD.map(r => ({ item: r.item, qty: String(r.qty || ''), unit: r.unit || '', price: String(r.price || ''), store: r.store || '', receiptUrl: r.receipt || '' })))
          if (rab.created_at) setCreateDate(new Date(rab.created_at).toISOString().split('T')[0])
        }
      } catch (err) {
        console.error('Load RAB error:', err)
      }

      
      try {
        const wcRes = await authFetch(`${API_BASE}/rab/worker-costs/${projectId}`)
        const wcData = await wcRes.json()
        if (wcData.success && wcData.data && wcData.data.sectionC?.length > 0) {
          const workerItems = wcData.data.sectionC.map(r => ({ item: r.item, qty: String(r.qty), unit: r.unit || 'hari', price: String(r.price) }))
          
          const manualItems = savedSectionC.filter(r => r.item.trim())
          const manualNames = new Set(manualItems.map(r => r.item.trim().toLowerCase()))
          
          const updatedManual = manualItems.filter(r => !workerItems.some(w => w.item.trim().toLowerCase() === r.item.trim().toLowerCase()))
          setSectionC([...workerItems, ...updatedManual].length > 0 ? [...workerItems, ...updatedManual] : [emptyRow()])
        } else {
          
          setSectionC(savedSectionC.length > 0 ? savedSectionC : [emptyRow()])
        }
      } catch (err) {
        console.error('Load worker costs error:', err)
        setSectionC(savedSectionC.length > 0 ? savedSectionC : [emptyRow()])
      }

      setLoadingData(false)
    }
    loadRAB()
  }, [editRabId, projectId, authFetch])

  useEffect(() => {
    if (rabType === 'bayangan' && !existingRabId) {
      setRabNumber(generateRandomRab())
    }
  }, [rabType, existingRabId])

  
  useEffect(() => {
    if (!projectId || loadingData || mrItemsLoaded) return
    const loadMaterialItems = async () => {
      try {
        const res = await authFetch(`${API_BASE}/rab/material-items/${projectId}`)
        const data = await res.json()
        if (data.success && data.data && data.data.totalItems > 0) {
          setMrItemCount(data.data.totalItems)
          const mrA = (data.data.sectionA || []).map(r => ({ item: r.item, qty: r.qty, unit: r.unit || 'pcs', price: r.price, store: r.store || '', receiptUrl: '' }))
          const mrB = (data.data.sectionB || []).map(r => ({ item: r.item, qtyNeeded: r.qtyNeeded, qtyAvailable: r.qtyAvailable, unit: r.unit || 'pcs', price: r.price, store: '', receiptUrl: '' }))

          
          if (mrA.length > 0) {
            setSectionA(prev => {
              const existingNames = new Set(prev.filter(r => r.item.trim()).map(r => r.item.trim().toLowerCase()))
              const newItems = mrA.filter(r => !existingNames.has(r.item.trim().toLowerCase()))
              if (newItems.length === 0) return prev
              const hasContent = prev.some(r => r.item.trim())
              return hasContent ? [...prev, ...newItems] : newItems
            })
          }
          if (mrB.length > 0) {
            setSectionB(prev => {
              const existingNames = new Set(prev.filter(r => r.item.trim()).map(r => r.item.trim().toLowerCase()))
              const newItems = mrB.filter(r => !existingNames.has(r.item.trim().toLowerCase()))
              if (newItems.length === 0) return prev
              const hasContent = prev.some(r => r.item.trim())
              return hasContent ? [...prev, ...newItems] : newItems
            })
          }
        }
      } catch (err) {
        console.error('Load material request items error:', err)
      } finally {
        setMrItemsLoaded(true)
      }
    }
    loadMaterialItems()
  }, [projectId, loadingData, mrItemsLoaded, authFetch])



  const handleSave = async () => {
    if (!projectId) {
      setSaveError('Project ID tidak ditemukan. Buka halaman ini dari tombol BUAT RAB di project.')
      return
    }
    setSaving(true)
    setSaveError('')
    try {
      let finalRab = rabNumber
      if (rabType === 'nyata' && projectAoNumber) {
        finalRab = projectAoNumber.replace(/^AO-/i, 'RAB-')
        setRabNumber(finalRab)
      }

      let payload
      payload = {
        project_id: parseInt(projectId),
        rab_number: finalRab || null,
        notes: null,
        rab_type: rabType,
        sectionA, sectionB, sectionC, sectionD,
      }
      const url = existingRabId ? `${API_BASE}/rab/${existingRabId}` : `${API_BASE}/rab`
      const method = existingRabId ? 'PUT' : 'POST'
      const res = await authFetch(url, {
        method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      })
      const data = await res.json()
      if (!data.success) throw new Error(data.message)
      setSaved(true)
      setExistingRabId(data.data.id)
      if (data.data.rab_number) setRabNumber(data.data.rab_number)
      setTimeout(() => {
        if (window.opener) {
          window.close()
        } else {
          navigate(-1)
        }
      }, 1200)
    } catch (err) {
      setSaveError(err.message || 'Gagal menyimpan RAB.')
    } finally {
      setSaving(false)
    }
  }

  const addRow = (setter, type) => setter(prev => [...prev, type === 'B' ? emptyRowB() : emptyRow()])
  const removeRow = (setter, idx) => setter(prev => prev.length > 1 ? prev.filter((_, i) => i !== idx) : prev)
  const updateRow = (setter, idx, field, value) => {
    setter(prev => prev.map((row, i) => i === idx ? { ...row, [field]: value } : row))
  }

  const calcTotal = (row) => {
    const q = parseFloat(row.qty) || 0
    const p = parseFloat(row.price) || 0
    return q * p
  }

  const sectionSum = (rows) => rows.reduce((sum, row) => sum + calcTotal(row), 0)

  const calcBFromWarehouse = (row) => {
    const needed = parseFloat(row.qtyNeeded) || 0
    const available = parseFloat(row.qtyAvailable) || 0
    return Math.min(needed, available)
  }
  const calcBToBuy = (row) => {
    const needed = parseFloat(row.qtyNeeded) || 0
    const available = parseFloat(row.qtyAvailable) || 0
    return Math.max(0, needed - available)
  }
  const sectionBTracking = useMemo(() =>
    sectionB.reduce((sum, row) => sum + calcBFromWarehouse(row) * (parseFloat(row.price) || 0), 0)
  , [sectionB])
  const sectionBBuy = useMemo(() =>
    sectionB.reduce((sum, row) => sum + calcBToBuy(row) * (parseFloat(row.price) || 0), 0)
  , [sectionB])

  const overflowItems = useMemo(() => {
    const items = []
    sectionB.forEach((row, bIdx) => {
      const toBuy = calcBToBuy(row)
      if (toBuy > 0 && (row.item || '').trim()) {
        items.push({ bIdx, item: row.item, qty: toBuy, unit: row.unit, price: row.price, store: row.store || '', receiptUrl: row.receiptUrl || '' })
      }
    })
    return items
  }, [sectionB])

  const sectionBItemNames = useMemo(() => {
    const names = new Set()
    sectionB.forEach(row => {
      const name = (row.item || '').trim().toLowerCase()
      if (name) names.add(name)
    })
    return names
  }, [sectionB])

  const duplicateItems = useMemo(() =>
    sectionA.filter(row => {
      const name = (row.item || '').trim().toLowerCase()
      return name && sectionBItemNames.has(name)
    }).map(row => row.item)
  , [sectionA, sectionBItemNames])

  const sectionAFiltered = useMemo(() =>
    sectionA.filter(row => {
      const name = (row.item || '').trim().toLowerCase()
      return !name || !sectionBItemNames.has(name)
    }).reduce((sum, row) => sum + calcTotal(row), 0)
  , [sectionA, sectionBItemNames])

  const sectionDTotal = useMemo(() => sectionSum(sectionD), [sectionD])
  const grandTotal = useMemo(() => sectionAFiltered + sectionBBuy + sectionSum(sectionC) + sectionDTotal, [sectionAFiltered, sectionBBuy, sectionC, sectionDTotal])

  const sections = []

  return (
    <div className="w-full h-full bg-white rounded-xl p-3 sm:p-6 text-gray-900 flex flex-col shadow-sm border border-gray-200 overflow-hidden">
      
      { }
      <div className="flex items-center justify-between mb-5 shrink-0">
        <div className="flex items-center gap-3">
          <button
            onClick={() => {
              if (window.opener) window.close()
              else navigate(-1)
            }}
            className="w-9 h-9 rounded-lg bg-gray-100 flex items-center justify-center hover:bg-gray-200 transition"
          >
            <FiArrowLeft size={18} />
          </button>
          <div>
            <h2 className="font-bold text-lg sm:text-xl flex items-center gap-2">
              <FiFileText className="text-blue-400" size={20} />
              {existingRabId ? 'Edit RAB' : 'Buat RAB Baru'}
              {rabType === 'bayangan' && <span className="text-xs bg-amber-100 text-amber-600 px-2 py-0.5 rounded font-medium">Bayangan</span>}
            </h2>
            <p className="text-xs text-gray-500 mt-0.5">
              {rabType === 'bayangan' ? 'RAB Bayangan (Estimasi)' : 'Rencana Anggaran Biaya'}
              {rabNumber && <span className="ml-2 text-blue-400 font-semibold">• {rabNumber}</span>}
            </p>
          </div>
        </div>
      </div>

      { }
      <div className="flex-1 overflow-auto custom-scrollbar min-h-0 space-y-5">

        { }
        <div className="bg-gray-50 border border-gray-200 rounded-xl p-4 sm:p-5">
          <h3 className="text-sm font-semibold text-gray-600 uppercase tracking-wider mb-4">Informasi Project</h3>
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <label className="block text-xs text-gray-500 mb-1.5">Nama Project <span className="text-red-400">*</span></label>
              <input
                type="text"
                value={projectName}
                onChange={e => setProjectName(e.target.value)}
                placeholder="Masukkan nama project..."
                className="w-full bg-white border border-gray-300 rounded-lg px-3.5 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500/50 transition"
              />
            </div>
            <div>
              <label className="block text-xs text-gray-500 mb-1.5">No. RAB</label>
              <input
                type="text"
                value={rabNumber}
                onChange={e => setRabNumber(e.target.value)}
                placeholder={rabType === 'bayangan' ? 'Akan diisi otomatis' : 'Contoh: AO-12345 atau RAB-001-ASA-2026'}
                className="w-full bg-white border border-gray-300 rounded-lg px-3.5 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500/50 transition"
                readOnly={rabType === 'bayangan' && !existingRabId}
              />
              {rabType === 'nyata' && projectAoNumber && (
                <p className="text-xs text-gray-400 mt-1">Terisi dari AO Number: {projectAoNumber}</p>
              )}
            </div>
            <div>
              <label className="block text-xs text-gray-500 mb-1.5">Tanggal Dibuat</label>
              <input
                type="date"
                value={createDate}
                onChange={e => setCreateDate(e.target.value)}
                className="w-full bg-white border border-gray-300 rounded-lg px-3.5 py-2.5 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500/50 transition"
              />
            </div>
          </div>
        </div>

        { }
        {mrItemCount > 0 && (
          <div className="bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-3 flex items-start gap-3">
            <FiCheck className="text-emerald-500 mt-0.5 shrink-0" size={16} />
            <div>
              <p className="text-sm font-medium text-emerald-700">Data Material Request Otomatis Terisi</p>
              <p className="text-xs text-emerald-600 mt-0.5">{mrItemCount} item dari material request yang sudah diproses telah otomatis dimasukkan ke Section A &amp; B. Anda tetap bisa mengedit.</p>
            </div>
          </div>
        )}

        { }
        <div className="bg-gray-50 border border-gray-200 rounded-xl p-4 sm:p-5">
          <div className="flex items-center justify-between mb-4">
            <h3 className="text-sm font-semibold flex items-center gap-2 text-blue-400">
              <FiPackage size={16} />
              <span className="text-gray-700">A.</span> Pembelian Material dari Toko Lain
            </h3>
            <div className="text-xs text-gray-500">
              Subtotal: <span className="text-gray-900 font-semibold">{formatRupiah(sectionAFiltered + sectionBBuy)}</span>
              {duplicateItems.length > 0 && <span className="text-amber-400 ml-1">(duplikat tidak dihitung)</span>}
            </div>
          </div>
          <div className="overflow-x-auto rounded-lg border border-gray-200">
            <table className="w-full text-sm min-w-[900px]">
              <thead>
                <tr className="bg-gray-100 text-gray-600 text-xs uppercase tracking-wider">
                  <th className="py-2.5 px-3 text-center w-14 font-medium">No</th>
                  <th className="py-2.5 px-3 text-left font-medium">Item</th>
                  <th className="py-2.5 px-3 text-center w-20 font-medium">Qty</th>
                  <th className="py-2.5 px-3 text-left w-28 font-medium">Unit</th>
                  <th className="py-2.5 px-3 text-right w-40 font-medium">Harga/Pcs</th>
                  <th className="py-2.5 px-3 text-right w-40 font-medium">Total</th>
                  <th className="py-2.5 px-3 text-left w-36 font-medium">Toko</th>
                  <th className="py-2.5 px-3 text-center w-20 font-medium">Struk</th>
                  <th className="py-2.5 px-3 text-center w-12"></th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {sectionA.map((row, idx) => {
                  const total = calcTotal(row)
                  return (
                    <tr key={idx} className="hover:bg-gray-50 transition">
                      <td className="py-2 px-3 text-center text-gray-500 font-mono text-xs">{idx + 1}</td>
                      <td className="py-2 px-3"><input type="text" value={row.item} onChange={e => updateRow(setSectionA, idx, 'item', e.target.value)} placeholder="Nama item..." className="w-full bg-transparent border-b border-gray-300 focus:border-blue-500 py-1 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none transition" /></td>
                      <td className="py-2 px-3"><input type="number" value={row.qty} onChange={e => updateRow(setSectionA, idx, 'qty', e.target.value)} placeholder="0" min="0" className="w-full bg-transparent border-b border-gray-300 focus:border-blue-500 py-1 text-sm text-gray-900 text-center placeholder:text-gray-400 focus:outline-none transition" /></td>
                      <td className="py-2 px-3"><input type="text" value={row.unit} onChange={e => updateRow(setSectionA, idx, 'unit', e.target.value)} placeholder="pcs, set..." className="w-full bg-transparent border-b border-gray-300 focus:border-blue-500 py-1 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none transition" /></td>
                      <td className="py-2 px-3">
                        <div className="flex items-center border-b border-gray-300 focus-within:border-blue-500 transition">
                          <span className="text-xs text-gray-400 pr-1.5 shrink-0">Rp</span>
                          <input type="text" value={row.price ? formatThousand(row.price) : ''} onChange={e => updateRow(setSectionA, idx, 'price', parseThousand(e.target.value))} placeholder="0" className="w-full bg-transparent py-1 text-sm text-gray-900 text-right placeholder:text-gray-400 focus:outline-none" />
                        </div>
                      </td>
                      <td className="py-2 px-3 text-right text-gray-900 font-medium text-xs whitespace-nowrap">{total > 0 ? formatRupiah(total) : <span className="text-gray-500">-</span>}</td>
                      <td className="py-2 px-3"><input type="text" value={row.store || ''} onChange={e => updateRow(setSectionA, idx, 'store', e.target.value)} placeholder="Nama toko..." className="w-full bg-transparent border-b border-gray-300 focus:border-blue-500 py-1 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none transition" /></td>
                      <td className="py-2 px-3 text-center">
                        <div className="flex items-center justify-center gap-1">
                          {row.receiptUrl ? (
                            <a href={`${API_BASE.replace('/api', '')}/uploads/receipts/${row.receiptUrl}`} target="_blank" rel="noreferrer" className="text-green-400 hover:text-green-300 transition" title="Lihat struk"><FiImage size={14} /></a>
                          ) : null}
                          <label className="cursor-pointer text-gray-500 hover:text-blue-400 transition" title="Upload struk">
                            <FiUpload size={13} />
                            <input type="file" className="hidden" accept="image/*,.pdf" onChange={async (e) => {
                              const file = e.target.files[0]
                              if (!file) return
                              const formData = new FormData()
                              formData.append('receipt', file)
                              try {
                                const res = await authFetch(`${API_BASE}/rab/upload-receipt`, { method: 'POST', body: formData })
                                const data = await res.json()
                                if (data.success) updateRow(setSectionA, idx, 'receiptUrl', data.filename)
                              } catch (err) { console.error('Upload receipt error:', err) }
                            }} />
                          </label>
                        </div>
                      </td>
                      <td className="py-2 px-3 text-center">{sectionA.length > 1 && <button type="button" onClick={() => removeRow(setSectionA, idx)} className="text-gray-500 hover:text-red-400 transition p-1"><FiTrash2 size={14} /></button>}</td>
                    </tr>
                  )
                })}
                {overflowItems.length > 0 && (
                  <>
                    <tr>
                      <td colSpan={9} className="py-2 px-3">
                        <div className="flex items-center gap-2 text-[11px] text-blue-400">
                          <div className="flex-1 border-t border-dashed border-blue-300" />
                          <FiShoppingCart size={11} />
                          <span className="font-medium">Tambahan dari Gudang (stok kurang)</span>
                          <div className="flex-1 border-t border-dashed border-blue-300" />
                        </div>
                      </td>
                    </tr>
                    {overflowItems.map((ov, idx) => {
                      const total = (parseFloat(ov.qty) || 0) * (parseFloat(ov.price) || 0)
                      return (
                        <tr key={`ov-${ov.bIdx}`} className="hover:bg-blue-50 transition bg-blue-50/70">
                          <td className="py-2 px-3 text-center text-blue-400 font-mono text-xs">{sectionA.length + idx + 1}</td>
                          <td className="py-2 px-3">
                            <div className="flex items-center gap-1.5">
                              <span className="text-sm text-blue-600">{ov.item || '-'}</span>
                              <span className="text-[9px] bg-blue-100 text-blue-600 px-1.5 py-0.5 rounded font-medium shrink-0">GUDANG</span>
                            </div>
                          </td>
                          <td className="py-2 px-3 text-center text-blue-600 text-sm">{ov.qty}</td>
                          <td className="py-2 px-3 text-blue-600 text-sm">{ov.unit || 'pcs'}</td>
                          <td className="py-2 px-3 text-right text-blue-600 text-sm">{ov.price ? formatRupiah(ov.price) : '-'}</td>
                          <td className="py-2 px-3 text-right text-blue-600 font-medium text-xs whitespace-nowrap">{total > 0 ? formatRupiah(total) : <span className="text-gray-500">-</span>}</td>
                          <td className="py-2 px-3"><input type="text" value={ov.store} onChange={e => updateRow(setSectionB, ov.bIdx, 'store', e.target.value)} placeholder="Nama toko..." className="w-full bg-transparent border-b border-blue-300 focus:border-blue-500 py-1 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none transition" /></td>
                          <td className="py-2 px-3 text-center">
                            <div className="flex items-center justify-center gap-1">
                              {ov.receiptUrl ? (
                                <a href={`${API_BASE.replace('/api', '')}/uploads/receipts/${ov.receiptUrl}`} target="_blank" rel="noreferrer" className="text-green-400 hover:text-green-300 transition" title="Lihat struk"><FiImage size={14} /></a>
                              ) : null}
                              <label className="cursor-pointer text-gray-500 hover:text-blue-400 transition" title="Upload struk">
                                <FiUpload size={13} />
                                <input type="file" className="hidden" accept="image/*,.pdf" onChange={async (e) => {
                                  const file = e.target.files[0]
                                  if (!file) return
                                  const formData = new FormData()
                                  formData.append('receipt', file)
                                  try {
                                    const res = await authFetch(`${API_BASE}/rab/upload-receipt`, { method: 'POST', body: formData })
                                    const data = await res.json()
                                    if (data.success) updateRow(setSectionB, ov.bIdx, 'receiptUrl', data.filename)
                                  } catch (err) { console.error('Upload receipt error:', err) }
                                }} />
                              </label>
                            </div>
                          </td>
                          <td></td>
                        </tr>
                      )
                    })}
                  </>
                )}
              </tbody>
            </table>
          </div>
          <button type="button" onClick={() => addRow(setSectionA)} className="mt-3 flex items-center gap-1.5 text-xs text-gray-500 hover:text-blue-500 transition font-medium"><FiPlus size={14} /> Tambah Baris</button>

          { }
          {duplicateItems.length > 0 && (
            <div className="mt-3 bg-amber-50 border border-amber-200 rounded-lg px-4 py-2.5 flex items-start gap-2.5">
              <FiAlertCircle className="text-amber-400 shrink-0 mt-0.5" size={14} />
              <div className="text-xs">
                <p className="text-amber-400 font-medium mb-0.5">Item berikut sudah ada di Section B (Gudang):</p>
                {duplicateItems.map((name, i) => (
                  <p key={i} className="text-amber-600">• {name}</p>
                ))}
                <p className="text-amber-600 mt-1">Item duplikat di Section A <b>tidak dihitung</b> ke Grand Total karena sudah dihitung di Section B.</p>
              </div>
            </div>
          )}
        </div>

        { }
        <div className="bg-gray-50 border border-gray-200 rounded-xl p-4 sm:p-5">
          <div className="flex items-center justify-between mb-2">
            <h3 className="text-sm font-semibold flex items-center gap-2 text-amber-400">
              <FiArchive size={16} />
              <span className="text-gray-700">B.</span> Material dari Gudang
            </h3>
            <div className="flex items-center gap-4 text-xs">
              <span className="text-gray-500">Dari gudang: <span className="text-amber-400 font-semibold">{formatRupiah(sectionBTracking)}</span> <span className="text-[10px] text-amber-500/60">(tracking)</span></span>
            </div>
          </div>
          <p className="text-[11px] text-gray-500 mb-4">Input total kebutuhan & stok gudang. Jika stok tidak cukup, sisa otomatis masuk ke biaya pembelian.</p>

          <div className="overflow-x-auto rounded-lg border border-gray-200">
            <table className="w-full text-sm min-w-[850px]">
              <thead>
                <tr className="bg-gray-100 text-gray-600 text-xs uppercase tracking-wider">
                  <th className="py-2.5 px-3 text-center w-14 font-medium">No</th>
                  <th className="py-2.5 px-3 text-left font-medium">Item</th>
                  <th className="py-2.5 px-3 text-center w-24 font-medium">Dibutuhkan</th>
                  <th className="py-2.5 px-3 text-center w-24 font-medium">Stok</th>
                  <th className="py-2.5 px-3 text-left w-24 font-medium">Unit</th>
                  <th className="py-2.5 px-3 text-right w-36 font-medium">Harga/Pcs</th>
                  <th className="py-2.5 px-3 text-center w-48 font-medium">Info</th>
                  <th className="py-2.5 px-3 text-center w-12"></th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {sectionB.map((row, idx) => {
                  const needed = parseFloat(row.qtyNeeded) || 0
                  const available = parseFloat(row.qtyAvailable) || 0
                  const fromWarehouse = calcBFromWarehouse(row)
                  const toBuy = calcBToBuy(row)
                  const price = parseFloat(row.price) || 0
                  const hasItem = needed > 0
                  return (
                    <tr key={idx} className="hover:bg-gray-50 transition">
                      <td className="py-2 px-3 text-center text-gray-500 font-mono text-xs">{idx + 1}</td>
                      <td className="py-2 px-3"><input type="text" value={row.item} onChange={e => updateRow(setSectionB, idx, 'item', e.target.value)} placeholder="Nama item..." className="w-full bg-transparent border-b border-gray-300 focus:border-blue-500 py-1 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none transition" /></td>
                      <td className="py-2 px-3"><input type="number" value={row.qtyNeeded} onChange={e => updateRow(setSectionB, idx, 'qtyNeeded', e.target.value)} placeholder="0" min="0" className="w-full bg-transparent border-b border-gray-300 focus:border-blue-500 py-1 text-sm text-gray-900 text-center placeholder:text-gray-400 focus:outline-none transition" /></td>
                      <td className="py-2 px-3"><input type="number" value={row.qtyAvailable} onChange={e => updateRow(setSectionB, idx, 'qtyAvailable', e.target.value)} placeholder="0" min="0" className="w-full bg-transparent border-b border-gray-300 focus:border-blue-500 py-1 text-sm text-gray-900 text-center placeholder:text-gray-400 focus:outline-none transition" /></td>
                      <td className="py-2 px-3"><input type="text" value={row.unit} onChange={e => updateRow(setSectionB, idx, 'unit', e.target.value)} placeholder="pcs, set..." className="w-full bg-transparent border-b border-gray-300 focus:border-blue-500 py-1 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none transition" /></td>
                      <td className="py-2 px-3">
                        <div className="flex items-center border-b border-gray-300 focus-within:border-blue-500 transition">
                          <span className="text-xs text-gray-400 pr-1.5 shrink-0">Rp</span>
                          <input type="text" value={row.price ? formatThousand(row.price) : ''} onChange={e => updateRow(setSectionB, idx, 'price', parseThousand(e.target.value))} placeholder="0" className="w-full bg-transparent py-1 text-sm text-gray-900 text-right placeholder:text-gray-400 focus:outline-none" />
                        </div>
                      </td>
                      <td className="py-2 px-2">
                        {hasItem ? (
                          <div className="flex flex-col gap-0.5 text-[10px]">
                            <span className="flex items-center justify-center gap-1 text-amber-400">
                              <FiArchive size={10} /> Gudang: {fromWarehouse} {row.unit || 'pcs'}
                              {price > 0 && <span className="text-amber-500/60">({formatRupiah(fromWarehouse * price)})</span>}
                            </span>
                            {toBuy > 0 ? (
                              <span className="flex items-center justify-center gap-1 text-blue-400">
                                <FiShoppingCart size={10} /> Beli: {toBuy} {row.unit || 'pcs'} → Section A
                              </span>
                            ) : (
                              <span className="text-green-400 text-center">✓ Stok cukup</span>
                            )}
                          </div>
                        ) : <span className="text-gray-600 text-xs text-center block">-</span>}
                      </td>
                      <td className="py-2 px-3 text-center">{sectionB.length > 1 && <button type="button" onClick={() => removeRow(setSectionB, idx)} className="text-gray-500 hover:text-red-400 transition p-1"><FiTrash2 size={14} /></button>}</td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
          <button type="button" onClick={() => addRow(setSectionB, 'B')} className="mt-3 flex items-center gap-1.5 text-xs text-gray-500 hover:text-amber-500 transition font-medium"><FiPlus size={14} /> Tambah Baris</button>

          {overflowItems.length > 0 && (
            <div className="mt-3 bg-blue-50 border border-blue-200 rounded-lg px-4 py-2.5 flex items-start gap-2.5">
              <FiShoppingCart className="text-blue-400 shrink-0 mt-0.5" size={14} />
              <p className="text-xs text-blue-400">Item yang stoknya kurang otomatis masuk ke Section A (Pembelian) di atas. Silakan isi nama toko & upload struk di sana.</p>
            </div>
          )}
        </div>

        { }
        <div className="bg-gray-50 border border-gray-200 rounded-xl p-4 sm:p-5">
          <div className="flex items-center justify-between mb-4">
            <h3 className="text-sm font-semibold flex items-center gap-2 text-teal-400">
              <FiUsers size={16} />
              <span className="text-gray-700">C.</span> Pekerja / SPK
            </h3>
            <div className="text-xs text-gray-500">
              Subtotal: <span className="text-gray-900 font-semibold">{formatRupiah(sectionSum(sectionC))}</span>
            </div>
          </div>
          <div className="overflow-x-auto rounded-lg border border-gray-200">
            <table className="w-full text-sm min-w-[700px]">
              <thead>
                <tr className="bg-gray-100 text-gray-600 text-xs uppercase tracking-wider">
                  <th className="py-2.5 px-3 text-center w-14 font-medium">No</th>
                  <th className="py-2.5 px-3 text-left font-medium">Item</th>
                  <th className="py-2.5 px-3 text-center w-20 font-medium">Qty</th>
                  <th className="py-2.5 px-3 text-left w-28 font-medium">Unit</th>
                  <th className="py-2.5 px-3 text-right w-40 font-medium">Harga/Pcs</th>
                  <th className="py-2.5 px-3 text-right w-40 font-medium">Total</th>
                  <th className="py-2.5 px-3 text-center w-12"></th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {sectionC.map((row, idx) => {
                  const total = calcTotal(row)
                  return (
                    <tr key={idx} className="hover:bg-gray-50 transition">
                      <td className="py-2 px-3 text-center text-gray-500 font-mono text-xs">{idx + 1}</td>
                      <td className="py-2 px-3"><input type="text" value={row.item} onChange={e => updateRow(setSectionC, idx, 'item', e.target.value)} placeholder="Nama item..." className="w-full bg-transparent border-b border-gray-300 focus:border-blue-500 py-1 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none transition" /></td>
                      <td className="py-2 px-3"><input type="number" value={row.qty} onChange={e => updateRow(setSectionC, idx, 'qty', e.target.value)} placeholder="0" min="0" className="w-full bg-transparent border-b border-gray-300 focus:border-blue-500 py-1 text-sm text-gray-900 text-center placeholder:text-gray-400 focus:outline-none transition" /></td>
                      <td className="py-2 px-3"><input type="text" value={row.unit} onChange={e => updateRow(setSectionC, idx, 'unit', e.target.value)} placeholder="orang, hari..." className="w-full bg-transparent border-b border-gray-300 focus:border-blue-500 py-1 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none transition" /></td>
                      <td className="py-2 px-3">
                        <div className="flex items-center border-b border-gray-300 focus-within:border-blue-500 transition">
                          <span className="text-xs text-gray-400 pr-1.5 shrink-0">Rp</span>
                          <input type="text" value={row.price ? formatThousand(row.price) : ''} onChange={e => updateRow(setSectionC, idx, 'price', parseThousand(e.target.value))} placeholder="0" className="w-full bg-transparent py-1 text-sm text-gray-900 text-right placeholder:text-gray-400 focus:outline-none" />
                        </div>
                      </td>
                      <td className="py-2 px-3 text-right text-gray-900 font-medium text-xs whitespace-nowrap">{total > 0 ? formatRupiah(total) : <span className="text-gray-500">-</span>}</td>
                      <td className="py-2 px-3 text-center">{sectionC.length > 1 && <button type="button" onClick={() => removeRow(setSectionC, idx)} className="text-gray-500 hover:text-red-400 transition p-1"><FiTrash2 size={14} /></button>}</td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
          <button type="button" onClick={() => addRow(setSectionC)} className="mt-3 flex items-center gap-1.5 text-xs text-gray-500 hover:text-teal-500 transition font-medium"><FiPlus size={14} /> Tambah Baris</button>
        </div>

        { }
        <div className="bg-gray-50 border border-gray-200 rounded-xl p-4 sm:p-5">
          <div className="flex items-center justify-between mb-4">
            <h3 className="text-sm font-semibold flex items-center gap-2 text-orange-400">
              <FiFileText size={16} />
              <span className="text-gray-700">D.</span> RAB Lainnya (Global)
            </h3>
            <div className="text-xs text-gray-500">
              Subtotal: <span className="text-gray-900 font-semibold">{formatRupiah(sectionDTotal)}</span>
            </div>
          </div>
          <p className="text-[11px] text-gray-500 mb-4">Estimasi biaya lainnya yang belum termasuk di Section A, B, dan C.</p>
          <div className="overflow-x-auto rounded-lg border border-gray-200">
            <table className="w-full text-sm min-w-[900px]">
              <thead>
                <tr className="bg-gray-100 text-gray-600 text-xs uppercase tracking-wider">
                  <th className="py-2.5 px-3 text-center w-14 font-medium">No</th>
                  <th className="py-2.5 px-3 text-left font-medium">Item</th>
                  <th className="py-2.5 px-3 text-center w-20 font-medium">Qty</th>
                  <th className="py-2.5 px-3 text-left w-28 font-medium">Unit</th>
                  <th className="py-2.5 px-3 text-right w-40 font-medium">Harga/Pcs</th>
                  <th className="py-2.5 px-3 text-right w-40 font-medium">Total</th>
                  <th className="py-2.5 px-3 text-left w-36 font-medium">Toko</th>
                  <th className="py-2.5 px-3 text-center w-20 font-medium">Struk</th>
                  <th className="py-2.5 px-3 text-center w-12"></th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {sectionD.map((row, idx) => {
                  const total = calcTotal(row)
                  return (
                    <tr key={idx} className="hover:bg-gray-50 transition">
                      <td className="py-2 px-3 text-center text-gray-500 font-mono text-xs">{idx + 1}</td>
                      <td className="py-2 px-3"><input type="text" value={row.item} onChange={e => updateRow(setSectionD, idx, 'item', e.target.value)} placeholder="Nama item..." className="w-full bg-transparent border-b border-gray-300 focus:border-orange-500 py-1 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none transition" /></td>
                      <td className="py-2 px-3"><input type="number" value={row.qty} onChange={e => updateRow(setSectionD, idx, 'qty', e.target.value)} placeholder="0" min="0" className="w-full bg-transparent border-b border-gray-300 focus:border-orange-500 py-1 text-sm text-gray-900 text-center placeholder:text-gray-400 focus:outline-none transition" /></td>
                      <td className="py-2 px-3"><input type="text" value={row.unit} onChange={e => updateRow(setSectionD, idx, 'unit', e.target.value)} placeholder="pcs, set..." className="w-full bg-transparent border-b border-gray-300 focus:border-orange-500 py-1 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none transition" /></td>
                      <td className="py-2 px-3">
                        <div className="flex items-center border-b border-gray-300 focus-within:border-orange-500 transition">
                          <span className="text-xs text-gray-400 pr-1.5 shrink-0">Rp</span>
                          <input type="text" value={row.price ? formatThousand(row.price) : ''} onChange={e => updateRow(setSectionD, idx, 'price', parseThousand(e.target.value))} placeholder="0" className="w-full bg-transparent py-1 text-sm text-gray-900 text-right placeholder:text-gray-400 focus:outline-none" />
                        </div>
                      </td>
                      <td className="py-2 px-3 text-right text-gray-900 font-medium text-xs whitespace-nowrap">{total > 0 ? formatRupiah(total) : <span className="text-gray-500">-</span>}</td>
                      <td className="py-2 px-3"><input type="text" value={row.store || ''} onChange={e => updateRow(setSectionD, idx, 'store', e.target.value)} placeholder="Nama toko..." className="w-full bg-transparent border-b border-gray-300 focus:border-orange-500 py-1 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none transition" /></td>
                      <td className="py-2 px-3 text-center">
                        <div className="flex items-center justify-center gap-1">
                          {row.receiptUrl ? (
                            <a href={`${API_BASE.replace('/api', '')}/uploads/receipts/${row.receiptUrl}`} target="_blank" rel="noreferrer" className="text-green-400 hover:text-green-300 transition" title="Lihat struk"><FiImage size={14} /></a>
                          ) : null}
                          <label className="cursor-pointer text-gray-500 hover:text-orange-400 transition" title="Upload struk">
                            <FiUpload size={13} />
                            <input type="file" className="hidden" accept="image/*,.pdf" onChange={async (e) => {
                              const file = e.target.files[0]
                              if (!file) return
                              const formData = new FormData()
                              formData.append('receipt', file)
                              try {
                                const res = await authFetch(`${API_BASE}/rab/upload-receipt`, { method: 'POST', body: formData })
                                const data = await res.json()
                                if (data.success) updateRow(setSectionD, idx, 'receiptUrl', data.filename)
                              } catch (err) { console.error('Upload receipt error:', err) }
                            }} />
                          </label>
                        </div>
                      </td>
                      <td className="py-2 px-3 text-center">{sectionD.length > 1 && <button type="button" onClick={() => removeRow(setSectionD, idx)} className="text-gray-500 hover:text-red-400 transition p-1"><FiTrash2 size={14} /></button>}</td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
          <button type="button" onClick={() => addRow(setSectionD)} className="mt-3 flex items-center gap-1.5 text-xs text-gray-500 hover:text-orange-500 transition font-medium"><FiPlus size={14} /> Tambah Baris</button>
        </div>

        { }
        <div className="bg-gray-50 border border-gray-200 rounded-xl p-4 sm:p-5">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
              <p className="text-xs text-gray-500 uppercase tracking-wider mb-1">Grand Total RAB</p>
              <p className="text-2xl sm:text-3xl font-bold text-gray-900">{formatRupiah(grandTotal)}</p>
            </div>
            <div className="text-right text-xs text-gray-400 space-y-1">
              <p>A. Material Beli: <span className="text-gray-900 font-medium">{formatRupiah(sectionAFiltered + sectionBBuy)}</span>{duplicateItems.length > 0 && <span className="text-amber-600 text-[10px] ml-1">(duplikat excluded)</span>}</p>
              <p className="flex items-center justify-end gap-1 text-amber-600"><FiArchive size={10} /> B. Dari Gudang: <span className="font-medium">{formatRupiah(sectionBTracking)}</span> <span className="text-[10px] opacity-60">(tracking)</span></p>
              <p>C. Pekerja / SPK: <span className="text-gray-900 font-medium">{formatRupiah(sectionSum(sectionC))}</span></p>
              <p>D. Lainnya (Global): <span className="text-gray-900 font-medium">{formatRupiah(sectionDTotal)}</span></p>
            </div>
          </div>
        </div>

        { }
        <div className="flex items-center gap-3 pb-2">
          <button
            type="button"
            onClick={() => {
              if (window.opener) window.close()
              else navigate(-1)
            }}
            className="px-5 py-2.5 rounded-lg bg-gray-200 text-gray-700 text-sm font-medium hover:bg-gray-300 transition"
          >
            Batal
          </button>
          <button
            type="button"
            onClick={handleSave}
            disabled={saving || saved}
            className={`px-6 py-2.5 rounded-lg text-white text-sm font-semibold transition flex items-center gap-2 disabled:opacity-60 ${saved ? 'bg-green-600' : 'bg-blue-600 hover:bg-blue-500'}`}
          >
            {saved ? <><FiCheck size={16} /> Tersimpan!</> : saving ? 'Menyimpan...' : <><FiSave size={16} /> {existingRabId ? 'Update RAB' : 'Simpan RAB'}</>}
          </button>
        </div>
        {saveError && (
          <div className="pb-2">
            <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-4 py-2">{saveError}</p>
          </div>
        )}
      </div>
    </div>
  )
}

export default CreateRAB
