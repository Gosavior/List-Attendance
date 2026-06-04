import React, { useEffect, useState, useCallback } from 'react'
import { FiTruck, FiPackage, FiCheckCircle, FiClock, FiMapPin, FiUser, FiBox, FiShoppingBag, FiChevronDown, FiChevronUp, FiAlertTriangle } from 'react-icons/fi'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import { useSocket } from '../context/SocketContext'

const API_BASE = import.meta.env.VITE_API_BASE || '/api'

export default function DeliverySchedule() {
  const { authFetch } = useAuth()
  const toast = useToast()
  const { onNotification } = useSocket()

  const [requests, setRequests] = useState([])
  const [summary, setSummary] = useState({ ready: 0, in_transit: 0, total: 0 })
  const [loading, setLoading] = useState(true)
  const [processing, setProcessing] = useState(null)
  const [expandedId, setExpandedId] = useState(null)
  const [filter, setFilter] = useState('all') 

  const fetchSchedule = useCallback(async () => {
    try {
      const res = await authFetch(`${API_BASE}/material-requests/driver/schedule`)
      const data = await res.json()
      if (data.success) {
        setRequests(data.data || [])
        setSummary(data.summary || { ready: 0, in_transit: 0, total: 0 })
      }
    } catch (err) {
      console.error('Fetch schedule error:', err)
    } finally {
      setLoading(false)
    }
  }, [authFetch])

  useEffect(() => { fetchSchedule() }, [fetchSchedule])

  
  useEffect(() => {
    const unsub = onNotification((data) => {
      if (data.type === 'delivery_schedule' || data.type === 'material_request') {
        fetchSchedule()
      }
    })
    return unsub
  }, [onNotification, fetchSchedule])

  
  useEffect(() => {
    const interval = setInterval(fetchSchedule, 60000)
    return () => clearInterval(interval)
  }, [fetchSchedule])

  const handlePickup = async (id) => {
    setProcessing(id)
    try {
      const res = await authFetch(`${API_BASE}/material-requests/${id}/driver-pickup`, { method: 'PUT' })
      const data = await res.json()
      if (data.success) {
        toast.success('Material sedang diantar')
        fetchSchedule()
      } else {
        toast.error(data.message || 'Gagal')
      }
    } catch (err) {
      toast.error('Terjadi kesalahan')
    } finally {
      setProcessing(null)
    }
  }

  const handleDelivered = async (id) => {
    setProcessing(id)
    try {
      const res = await authFetch(`${API_BASE}/material-requests/${id}/driver-delivered`, { method: 'PUT' })
      const data = await res.json()
      if (data.success) {
        toast.success('Material berhasil diantar!')
        fetchSchedule()
      } else {
        toast.error(data.message || 'Gagal')
      }
    } catch (err) {
      toast.error('Terjadi kesalahan')
    } finally {
      setProcessing(null)
    }
  }

  const filtered = requests.filter(r => {
    if (filter === 'ready') return r.status === 'admin_approved'
    if (filter === 'in_transit') return r.status === 'driver_pickup'
    return true
  })

  const formatDate = (d) => {
    if (!d) return '-'
    return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center h-full">
        <div className="w-8 h-8 border-4 border-slate-300 border-t-slate-600 rounded-full animate-spin"></div>
      </div>
    )
  }

  return (
    <div className="max-w-4xl mx-auto space-y-4">
      { }
      <div>
        <h1 className="text-xl font-bold text-gray-800 flex items-center gap-2">
          <FiTruck size={22} /> Jadwal Pengantaran
        </h1>
        <p className="text-sm text-gray-500 mt-1">Daftar material yang perlu diantar atau diambil dari toko</p>
      </div>

      { }
      <div className="grid grid-cols-3 gap-3">
        <div className="bg-white rounded-xl p-4 border border-gray-200 text-center">
          <p className="text-2xl font-bold text-amber-600">{summary.ready}</p>
          <p className="text-xs text-gray-500 mt-1">Siap Antar</p>
        </div>
        <div className="bg-white rounded-xl p-4 border border-gray-200 text-center">
          <p className="text-2xl font-bold text-blue-600">{summary.in_transit}</p>
          <p className="text-xs text-gray-500 mt-1">Dalam Perjalanan</p>
        </div>
        <div className="bg-white rounded-xl p-4 border border-gray-200 text-center">
          <p className="text-2xl font-bold text-gray-700">{summary.total}</p>
          <p className="text-xs text-gray-500 mt-1">Total</p>
        </div>
      </div>

      { }
      <div className="flex gap-2">
        {[
          { key: 'all', label: 'Semua', count: summary.total },
          { key: 'ready', label: 'Siap Antar', count: summary.ready },
          { key: 'in_transit', label: 'Dalam Perjalanan', count: summary.in_transit },
        ].map(f => (
          <button key={f.key} onClick={() => setFilter(f.key)}
            className={`px-4 py-2 rounded-lg text-sm font-semibold transition ${
              filter === f.key ? 'bg-slate-700 text-white' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200'
            }`}>
            {f.label} {f.count > 0 && <span className="ml-1 text-xs opacity-70">({f.count})</span>}
          </button>
        ))}
      </div>

      { }
      {filtered.length === 0 ? (
        <div className="bg-white rounded-xl p-12 text-center border border-gray-200">
          <FiTruck size={40} className="mx-auto text-gray-300 mb-3" />
          <p className="text-gray-500 font-medium">Tidak ada pengantaran</p>
        </div>
      ) : (
        <div className="space-y-3">
          {filtered.map(req => {
            const isExpanded = expandedId === req.id
            const hasPurchase = (req.items || []).some(i => i.source_type === 'purchase' || (i.qty_to_purchase && i.qty_to_purchase > 0))
            const isReady = req.status === 'admin_approved'
            const isInTransit = req.status === 'driver_pickup'

            return (
              <div key={req.id} className={`bg-white rounded-xl border overflow-hidden transition ${
                isReady ? 'border-amber-200' : 'border-blue-200'
              }`}>
                { }
                <div className="px-4 py-3 flex items-center gap-3 cursor-pointer" onClick={() => setExpandedId(isExpanded ? null : req.id)}>
                  <div className={`w-10 h-10 rounded-full flex items-center justify-center shrink-0 ${
                    isReady ? 'bg-amber-100 text-amber-600' : 'bg-blue-100 text-blue-600'
                  }`}>
                    {isReady ? <FiPackage size={18} /> : <FiTruck size={18} />}
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2">
                      <p className="text-sm font-bold text-gray-800 truncate">
                        #{req.id} — {req.project?.project_name || 'Unknown Project'}
                      </p>
                      {hasPurchase && (
                        <span className="shrink-0 px-2 py-0.5 bg-orange-100 text-orange-700 text-[10px] font-bold rounded-full flex items-center gap-1">
                          <FiShoppingBag size={10} /> Ambil dari Toko
                        </span>
                      )}
                    </div>
                    <div className="flex items-center gap-3 text-xs text-gray-500 mt-0.5">
                      <span className="flex items-center gap-1"><FiUser size={11} /> {req.requester_name}</span>
                      <span className="flex items-center gap-1"><FiClock size={11} /> {formatDate(req.admin_ready_at)}</span>
                      {req.project?.project_location && (
                        <span className="flex items-center gap-1"><FiMapPin size={11} /> {req.project.project_location}</span>
                      )}
                    </div>
                  </div>
                  <div className="flex items-center gap-2 shrink-0">
                    <span className={`px-3 py-1 rounded-full text-xs font-bold ${
                      isReady ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700'
                    }`}>
                      {isReady ? 'Siap Antar' : 'Dalam Perjalanan'}
                    </span>
                    {isExpanded ? <FiChevronUp size={16} className="text-gray-400" /> : <FiChevronDown size={16} className="text-gray-400" />}
                  </div>
                </div>

                { }
                {isExpanded && (
                  <div className="border-t border-gray-100">
                    { }
                    <div className="px-4 py-3 space-y-2">
                      <p className="text-xs font-bold text-gray-500 uppercase">Material ({(req.items || []).length} item)</p>
                      {(req.items || []).map((item, idx) => {
                        const fromWarehouse = item.qty_from_warehouse > 0
                        const fromPurchase = item.qty_to_purchase > 0

                        return (
                          <div key={idx} className="bg-gray-50 rounded-lg p-3 border border-gray-100">
                            <div className="flex justify-between items-start">
                              <div>
                                <p className="text-sm font-semibold text-gray-800">{item.material_name}</p>
                                <p className="text-xs text-gray-500 mt-0.5">Qty: {item.quantity}</p>
                              </div>
                            </div>
                            {(fromWarehouse || fromPurchase) && (
                              <div className="mt-2 flex flex-wrap gap-2">
                                {fromWarehouse && (
                                  <span className="inline-flex items-center gap-1 px-2 py-1 bg-teal-50 text-teal-700 text-xs rounded-md">
                                    <FiBox size={11} /> Gudang: {item.qty_from_warehouse}
                                  </span>
                                )}
                                {fromPurchase && (
                                  <span className="inline-flex items-center gap-1 px-2 py-1 bg-orange-50 text-orange-700 text-xs rounded-md">
                                    <FiShoppingBag size={11} /> Beli: {item.qty_to_purchase}
                                    {item.store_name && ` — ${item.store_name}`}
                                  </span>
                                )}
                              </div>
                            )}
                            {!fromWarehouse && !fromPurchase && (
                              <div className="mt-1">
                                <span className={`inline-flex items-center gap-1 px-2 py-1 text-xs rounded-md ${
                                  item.source_type === 'purchase' ? 'bg-orange-50 text-orange-700' : 'bg-teal-50 text-teal-700'
                                }`}>
                                  {item.source_type === 'purchase' ? <><FiShoppingBag size={11} /> Beli</> : <><FiBox size={11} /> Gudang</>}
                                  {item.store_name && ` — ${item.store_name}`}
                                </span>
                              </div>
                            )}
                          </div>
                        )
                      })}
                    </div>

                    { }
                    {hasPurchase && isReady && (
                      <div className="mx-4 mb-3 p-3 bg-orange-50 border border-orange-200 rounded-lg">
                        <p className="text-xs font-bold text-orange-700 flex items-center gap-1 mb-1">
                          <FiAlertTriangle size={12} /> Perlu Mengambil dari Toko
                        </p>
                        {(req.items || []).filter(i => i.qty_to_purchase > 0 || i.source_type === 'purchase').map((item, idx) => (
                          <p key={idx} className="text-xs text-orange-600 ml-4">
                            • {item.material_name} — {item.qty_to_purchase || item.quantity} pcs
                            {item.store_name && ` dari "${item.store_name}"`}
                          </p>
                        ))}
                      </div>
                    )}

                    { }
                    <div className="px-4 py-3 bg-gray-50 border-t border-gray-100 flex gap-2">
                      {isReady && (
                        <button onClick={() => handlePickup(req.id)} disabled={processing === req.id}
                          className="flex-1 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl text-sm transition flex items-center justify-center gap-2 disabled:opacity-50">
                          <FiTruck size={15} /> {hasPurchase ? 'Ambil & Antar' : 'Mulai Antar'}
                        </button>
                      )}
                      {isInTransit && (
                        <button onClick={() => handleDelivered(req.id)} disabled={processing === req.id}
                          className="flex-1 py-2.5 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl text-sm transition flex items-center justify-center gap-2 disabled:opacity-50">
                          <FiCheckCircle size={15} /> Sudah Diantar
                        </button>
                      )}
                    </div>
                  </div>
                )}
              </div>
            )
          })}
        </div>
      )}
    </div>
  )
}
