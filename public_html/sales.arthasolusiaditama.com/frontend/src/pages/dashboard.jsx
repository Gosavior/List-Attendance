import React, { useState, useEffect, useCallback } from 'react'
import { Link } from 'react-router-dom'
import { PieChart, Pie, Cell, Legend, ResponsiveContainer, Tooltip, BarChart, Bar, XAxis, YAxis, CartesianGrid, AreaChart, Area, ReferenceLine, ComposedChart, Line } from 'recharts'
import { FiUser, FiDollarSign, FiCheckCircle, FiXCircle, FiClock, FiAlertTriangle, FiActivity, FiTarget, FiChevronLeft, FiChevronRight, FiEdit3, FiSave } from 'react-icons/fi'
import { FaCrown, FaMedal, FaTrophy } from 'react-icons/fa'
import { useAuth } from '../context/AuthContext'

const API_BASE = import.meta.env.VITE_API_BASE || '/api'

const STATUS_COLORS = {
  PROSPECT: '#f59e0b',
  NEAREST: '#3b82f6',
  ONGOING: '#a855f7',
  DONE: '#84cc16',
  LOST: '#ef4444',
}

const STATUS_BG = {
  PROSPECT: 'bg-amber-500',
  NEAREST: 'bg-blue-500',
  ONGOING: 'bg-purple-500',
  DONE: 'bg-green-500',
  LOST: 'bg-red-500',
}

const formatRupiah = (num) => {
  if (!num || num === 0) return 'Rp 0'
  if (num >= 1e9) return `Rp ${(num / 1e9).toFixed(1)} M`
  if (num >= 1e6) return `Rp ${(num / 1e6).toFixed(0)} Jt`
  return `Rp ${num.toLocaleString('id-ID')}`
}


const StatusCard = ({ title, value, bgColor }) => (
  <div className={`${bgColor} rounded-lg p-3 sm:p-4 flex flex-col items-center justify-center text-white cursor-pointer transition-all duration-200 hover:scale-105 hover:shadow-lg hover:brightness-110`}>
    <span className='text-xs sm:text-sm font-semibold tracking-wide'>{title}</span>
    <span className='text-2xl sm:text-3xl font-bold'>{value}</span>
  </div>
)

const renderLegend = (props) => {
  const { payload } = props
  return (
    <ul className='flex flex-col gap-2'>
      {payload.map((entry, index) => (
        <li key={index} className='flex items-center gap-2 text-sm text-gray-700'>
          <span className='w-3 h-3 rounded-full shrink-0' style={{ backgroundColor: entry.color }} />
          {entry.value}
        </li>
      ))}
    </ul>
  )
}

const MONTH_NAMES = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']


const SalesLeaderboard = ({ authFetch, isAdmin }) => {
  const now = new Date()
  const [month, setMonth] = useState(now.getMonth() + 1)
  const [year, setYear] = useState(now.getFullYear())
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [editingTarget, setEditingTarget] = useState(false)
  const [targetInput, setTargetInput] = useState('')

  useEffect(() => {
    const load = async () => {
      setLoading(true)
      try {
        const res = await authFetch(`${API_BASE}/dashboard/sales-leaderboard?month=${month}&year=${year}`)
        const d = await res.json()
        if (d.success) setData(d.data)
      } catch (err) { console.error('Leaderboard error:', err) }
      finally { setLoading(false) }
    }
    load()
  }, [authFetch, month, year])

  const prevMonth = () => {
    if (month === 1) { setMonth(12); setYear(y => y - 1) }
    else setMonth(m => m - 1)
  }
  const nextMonth = () => {
    if (month === 12) { setMonth(1); setYear(y => y + 1) }
    else setMonth(m => m + 1)
  }

  const saveTarget = async () => {
    const val = parseFloat(targetInput.replace(/\./g, '').replace(/,/g, ''))
    if (isNaN(val) || val < 0) return
    try {
      await authFetch(`${API_BASE}/dashboard/sales-target`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ month, year, target_amount: val }),
      })
      setData(prev => prev ? { ...prev, target: val } : prev)
      setEditingTarget(false)
    } catch (err) { console.error('Save target error:', err) }
  }

  const getRank = (revenue, target) => {
    if (!target || target <= 0) return 'default'
    const pct = revenue / target
    if (pct >= 1) return 'gold'
    if (pct >= 0.75) return 'silver'
    if (pct >= 0.5) return 'bronze'
    return 'default'
  }

  const rankStyles = {
    gold: 'border-yellow-400 bg-gradient-to-r from-yellow-50 to-amber-50',
    silver: 'border-gray-300 bg-gradient-to-r from-gray-50 to-slate-100',
    bronze: 'border-amber-600 bg-gradient-to-r from-orange-50 to-amber-50',
    default: 'border-slate-600 bg-slate-700',
  }
  const rankTextColor = { gold: 'text-yellow-700', silver: 'text-gray-700', bronze: 'text-amber-800', default: 'text-white' }
  const rankSubColor = { gold: 'text-yellow-600', silver: 'text-gray-500', bronze: 'text-amber-600', default: 'text-gray-300' }
  const rankBarBg = { gold: 'bg-yellow-200', silver: 'bg-gray-200', bronze: 'bg-orange-200', default: 'bg-slate-500' }
  const rankBarFill = { gold: 'bg-yellow-500', silver: 'bg-gray-400', bronze: 'bg-amber-500', default: 'bg-green-400' }

  const target = data?.target || 0

  return (
    <div className="bg-slate-600 rounded-lg flex flex-col text-white font-bold overflow-hidden h-full">
      <div className='px-4 pt-3 pb-2 flex flex-col items-center'>
        <div className='flex items-center gap-2 text-lg'>
          <FiTarget size={18} className='text-yellow-400' />
          LEADERBOARD
        </div>
        { }
        <div className='flex items-center gap-2 mt-1'>
          <button onClick={prevMonth} className='p-0.5 hover:bg-slate-500 rounded transition'><FiChevronLeft size={16} /></button>
          <span className='text-xs font-medium text-gray-300 min-w-[120px] text-center'>{MONTH_NAMES[month - 1]} {year}</span>
          <button onClick={nextMonth} className='p-0.5 hover:bg-slate-500 rounded transition'><FiChevronRight size={16} /></button>
        </div>
        { }
        <div className='mt-1.5 flex items-center gap-1.5 text-xs font-normal'>
          <span className='text-gray-400'>Target:</span>
          {editingTarget && isAdmin ? (
            <div className='flex items-center gap-1'>
              <input
                type='text'
                value={targetInput}
                onChange={e => setTargetInput(e.target.value)}
                className='w-28 bg-slate-700 border border-slate-500 rounded px-2 py-0.5 text-xs text-white focus:outline-none focus:border-yellow-400'
                placeholder='2000000000'
                autoFocus
                onKeyDown={e => e.key === 'Enter' && saveTarget()}
              />
              <button onClick={saveTarget} className='text-green-400 hover:text-green-300'><FiSave size={13} /></button>
            </div>
          ) : (
            <span className='text-yellow-400 font-semibold'>
              {formatRupiah(target)}
              {isAdmin && <button onClick={() => { setTargetInput(String(target)); setEditingTarget(true) }} className='ml-1 text-gray-400 hover:text-yellow-400'><FiEdit3 size={11} /></button>}
            </span>
          )}
        </div>
      </div>
      <div className='flex-1 overflow-y-auto px-2 pb-3 custom-scrollbar'>
        {loading ? (
          <div className='flex justify-center py-6'><div className="animate-spin w-6 h-6 border-2 border-slate-400 border-t-white rounded-full" /></div>
        ) : !data?.leaderboard?.length ? (
          <p className='text-center text-gray-400 text-sm mt-6 font-normal'>Belum ada data bulan ini</p>
        ) : data.leaderboard.map((u, i) => {
          const rank = getRank(u.revenue, target)
          const pct = target > 0 ? Math.min(100, Math.round((u.revenue / target) * 100)) : 0
          return (
            <div key={u.user_id} className={`flex mt-2.5 w-11/12 mx-auto rounded-lg p-3 items-center gap-3 border-2 transition-all ${rankStyles[rank]}`}>
              <div className='shrink-0 flex flex-col items-center w-8'>
                {i === 0 ? (
                  <div className='relative'>
                    <FaTrophy className='text-yellow-500' size={22} />
                    <span className='absolute -top-1 -right-1 text-[9px] bg-yellow-500 text-white rounded-full w-3.5 h-3.5 flex items-center justify-center font-bold'>1</span>
                  </div>
                ) : i === 1 ? (
                  <FaMedal className='text-gray-400' size={20} />
                ) : i === 2 ? (
                  <FaMedal className='text-amber-600' size={20} />
                ) : (
                  <span className={`text-sm font-bold ${rankTextColor[rank]}`}>#{i + 1}</span>
                )}
              </div>
              <div className='flex-1 min-w-0'>
                <div className='flex items-center justify-between'>
                  <h5 className={`font-bold text-sm truncate ${rankTextColor[rank]}`}>{u.name}</h5>
                  <span className={`text-[10px] font-semibold ${rankSubColor[rank]}`}>{u.done} Done</span>
                </div>
                <div className={`text-xs font-semibold mt-0.5 ${rank === 'gold' ? 'text-yellow-600' : rank === 'silver' ? 'text-gray-600' : rank === 'bronze' ? 'text-amber-700' : 'text-green-400'}`}>
                  {formatRupiah(u.revenue)}
                </div>
                <div className='flex items-center gap-2 mt-1'>
                  <div className={`flex-1 rounded-full h-2.5 overflow-hidden ${rankBarBg[rank]}`}>
                    <div className={`h-full rounded-full transition-all duration-500 ${rankBarFill[rank]}`} style={{ width: `${pct}%` }} />
                  </div>
                  <span className={`text-[10px] font-semibold ${rankSubColor[rank]}`}>{pct}%</span>
                </div>
              </div>
            </div>
          )
        })}
      </div>
    </div>
  )
}


const TradingChart = ({ authFetch }) => {
  const [period, setPeriod] = useState('monthly')
  const [year, setYear] = useState(new Date().getFullYear())
  const [chartData, setChartData] = useState([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const load = async () => {
      setLoading(true)
      try {
        const url = period === 'yearly'
          ? `${API_BASE}/dashboard/chart-data?period=yearly`
          : `${API_BASE}/dashboard/chart-data?period=monthly&year=${year}`
        const res = await authFetch(url)
        const d = await res.json()
        if (d.success) setChartData(d.data)
      } catch (err) { console.error('Chart error:', err) }
      finally { setLoading(false) }
    }
    load()
  }, [authFetch, period, year])

  const CustomTooltip = ({ active, payload, label }) => {
    if (!active || !payload?.length) return null
    const d = payload[0]?.payload
    return (
      <div className='bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-xs shadow-xl'>
        <p className='text-white font-semibold mb-1'>{label}</p>
        <p className='text-green-400'>Revenue: {formatRupiah(d?.revenue || 0)}</p>
        <p className='text-red-400'>Lost: {formatRupiah(d?.lost || 0)}</p>
        {d?.target > 0 && <p className='text-yellow-400'>Target: {formatRupiah(d?.target)}</p>}
        <p className='text-gray-400'>Deals: {d?.deals || 0}</p>
      </div>
    )
  }

  return (
    <div className='bg-white rounded-lg shadow p-4'>
      <div className='flex items-center justify-between mb-3 flex-wrap gap-2'>
        <h3 className='font-bold text-sm text-gray-700 flex items-center gap-2'>
          <FiActivity className='text-indigo-500' size={16} />
          Revenue Chart
        </h3>
        <div className='flex items-center gap-2'>
          {period === 'monthly' && (
            <div className='flex items-center gap-1'>
              <button onClick={() => setYear(y => y - 1)} className='p-1 hover:bg-gray-100 rounded transition'><FiChevronLeft size={14} /></button>
              <span className='text-xs font-semibold text-gray-600 min-w-[40px] text-center'>{year}</span>
              <button onClick={() => setYear(y => y + 1)} className='p-1 hover:bg-gray-100 rounded transition'><FiChevronRight size={14} /></button>
            </div>
          )}
          <div className='flex bg-gray-100 rounded-lg p-0.5'>
            <button
              onClick={() => setPeriod('monthly')}
              className={`px-3 py-1 text-xs font-medium rounded-md transition ${period === 'monthly' ? 'bg-white shadow text-indigo-600' : 'text-gray-500 hover:text-gray-700'}`}
            >Bulanan</button>
            <button
              onClick={() => setPeriod('yearly')}
              className={`px-3 py-1 text-xs font-medium rounded-md transition ${period === 'yearly' ? 'bg-white shadow text-indigo-600' : 'text-gray-500 hover:text-gray-700'}`}
            >Tahunan</button>
          </div>
        </div>
      </div>

      {loading ? (
        <div className='h-[280px] flex items-center justify-center'>
          <div className="animate-spin w-6 h-6 border-2 border-gray-300 border-t-indigo-500 rounded-full" />
        </div>
      ) : chartData.length === 0 ? (
        <div className='h-[280px] flex items-center justify-center text-gray-400 text-sm'>Belum ada data</div>
      ) : (
        <ResponsiveContainer width='100%' height={280}>
          <ComposedChart data={chartData}>
            <defs>
              <linearGradient id='revenueGrad' x1='0' y1='0' x2='0' y2='1'>
                <stop offset='5%' stopColor='#6366f1' stopOpacity={0.3} />
                <stop offset='95%' stopColor='#6366f1' stopOpacity={0.02} />
              </linearGradient>
              <linearGradient id='lostGrad' x1='0' y1='0' x2='0' y2='1'>
                <stop offset='5%' stopColor='#ef4444' stopOpacity={0.2} />
                <stop offset='95%' stopColor='#ef4444' stopOpacity={0.02} />
              </linearGradient>
            </defs>
            <CartesianGrid strokeDasharray='3 3' stroke='#f0f0f0' />
            <XAxis dataKey='label' tick={{ fontSize: 11, fill: '#9ca3af' }} />
            <YAxis tick={{ fontSize: 11, fill: '#9ca3af' }} tickFormatter={v => v >= 1e9 ? `${(v / 1e9).toFixed(1)}M` : v >= 1e6 ? `${(v / 1e6).toFixed(0)}Jt` : v} />
            <Tooltip content={<CustomTooltip />} />
            <Area type='monotone' dataKey='revenue' stroke='#6366f1' strokeWidth={2} fill='url(#revenueGrad)' />
            <Area type='monotone' dataKey='lost' stroke='#ef4444' strokeWidth={1.5} fill='url(#lostGrad)' />
            <Line type='monotone' dataKey='target' stroke='#eab308' strokeWidth={2} strokeDasharray='6 3' dot={false} />
          </ComposedChart>
        </ResponsiveContainer>
      )}
      <div className='flex items-center justify-center gap-5 mt-2 text-[11px]'>
        <span className='flex items-center gap-1.5'><span className='w-3 h-0.5 bg-indigo-500 inline-block rounded' /> Revenue</span>
        <span className='flex items-center gap-1.5'><span className='w-3 h-0.5 bg-red-500 inline-block rounded' /> Lost</span>
        <span className='flex items-center gap-1.5'><span className='w-3 h-0.5 bg-yellow-500 inline-block rounded border-dashed' style={{borderTop:'2px dashed #eab308', height:0}} /> Target</span>
      </div>
    </div>
  )
}


const SalesDashboard = ({ authFetch }) => {
  const [stats, setStats] = useState(null)
  const [recent, setRecent] = useState([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const load = async () => {
      try {
        const [statsRes, recentRes] = await Promise.all([
          authFetch(`${API_BASE}/dashboard/stats`),
          authFetch(`${API_BASE}/dashboard/recent-projects?limit=5`),
        ])
        const statsData = await statsRes.json()
        const recentData = await recentRes.json()
        if (statsData.success) setStats(statsData.data)
        if (recentData.success) setRecent(recentData.data)
      } catch (err) {
        console.error('Sales dashboard error:', err)
      } finally {
        setLoading(false)
      }
    }
    load()
  }, [authFetch])

  if (loading || !stats) {
    return (
      <div className="flex-1 flex items-center justify-center text-gray-400">
        <div className="animate-spin w-8 h-8 border-3 border-stone-300 border-t-stone-600 rounded-full" />
      </div>
    )
  }

  const pieData = ['PROSPECT', 'NEAREST', 'ONGOING', 'LOST', 'DONE']
    .filter(s => stats[s] > 0)
    .map(s => ({ name: s, value: stats[s], color: STATUS_COLORS[s] }))

  return (
    <div className='flex-1 overflow-y-auto custom-scrollbar min-h-0 pr-1'>
      <div className='flex flex-col gap-4 sm:gap-6'>
        <div className='w-full bg-white rounded-lg shadow p-3 sm:p-4 flex flex-col lg:flex-row gap-4'>
          <div className='flex-1 flex items-center justify-center pr-0 lg:pr-14'>
            {pieData.length > 0 ? (
              <ResponsiveContainer width='100%' height={220}>
                <PieChart>
                  <Pie data={pieData} cx='40%' cy='50%' outerRadius={85} dataKey='value' stroke='none'>
                    {pieData.map((entry, index) => (
                      <Cell key={index} fill={entry.color} />
                    ))}
                  </Pie>
                  <Tooltip formatter={(value, name) => [value, name]} />
                  <Legend layout='vertical' align='right' verticalAlign='middle' content={renderLegend} />
                </PieChart>
              </ResponsiveContainer>
            ) : (
              <p className='text-gray-400 text-sm'>Belum ada project</p>
            )}
          </div>

          <div className='grid grid-cols-2 gap-2 sm:gap-4 w-full lg:w-[700px] shrink-0'>
            <StatusCard title='PROSPECT' value={stats.PROSPECT} bgColor='bg-amber-500'/>
            <StatusCard title='NEAREST' value={stats.NEAREST} bgColor='bg-blue-500' />
            <StatusCard title='ONGOING' value={stats.ONGOING} bgColor='bg-purple-500' />
            <StatusCard title='DONE' value={stats.DONE} bgColor='bg-green-500' />
          </div>
        </div>

        <div className='w-full flex flex-col md:flex-row gap-4 sm:gap-6 md:h-[320px]'>
          { }
          <div className="w-full md:w-1/2 bg-white h-[300px] md:h-full rounded-lg shadow flex flex-col overflow-hidden">
            <div className='flex items-center gap-2 px-4 pt-4 pb-2'>
              <FiActivity className='text-indigo-500' size={18} />
              <h3 className='font-bold text-gray-700 text-base'>Ringkasan Kamu</h3>
            </div>
            <div className='flex-1 overflow-y-auto custom-scrollbar px-4 pb-4 space-y-3'>
              <div className='bg-slate-50 border border-slate-200 rounded-xl p-3.5 flex items-center gap-3'>
                <div className='w-10 h-10 rounded-lg bg-slate-600 flex items-center justify-center shrink-0'>
                  <FiActivity className='text-white' size={18} />
                </div>
                <div className='flex-1 min-w-0'>
                  <p className='text-xs text-slate-400 font-medium'>Total Project</p>
                  <p className='text-2xl font-bold text-slate-700'>{stats.total}</p>
                </div>
              </div>
              <div className='bg-slate-50 border border-slate-200 rounded-xl p-3.5 flex items-center gap-3'>
                <div className='w-10 h-10 rounded-lg bg-slate-600 flex items-center justify-center shrink-0'>
                  <FiDollarSign className='text-white' size={18} />
                </div>
                <div className='flex-1 min-w-0'>
                  <p className='text-xs text-slate-400 font-medium'>Revenue (Done)</p>
                  <p className='text-lg font-bold text-slate-700 truncate'>{formatRupiah(stats.done_revenue)}</p>
                </div>
              </div>
              <div className='bg-slate-50 border border-slate-200 rounded-xl p-3.5 flex items-center gap-3'>
                <div className='w-10 h-10 rounded-lg bg-slate-600 flex items-center justify-center shrink-0'>
                  <FiCheckCircle className='text-white' size={18} />
                </div>
                <div className='flex-1 min-w-0'>
                  <p className='text-xs text-slate-400 font-medium'>Closing Rate</p>
                  <p className='text-2xl font-bold text-slate-700'>{stats.closing_rate}%</p>
                </div>
              </div>
              <div className='bg-slate-50 border border-slate-200 rounded-xl p-3.5 flex items-center gap-3'>
                <div className='w-10 h-10 rounded-lg bg-slate-600 flex items-center justify-center shrink-0'>
                  <FiXCircle className='text-white' size={18} />
                </div>
                <div className='flex-1 min-w-0'>
                  <p className='text-xs text-slate-400 font-medium'>Lost</p>
                  <p className='text-lg font-bold text-slate-700'>{stats.LOST} project <span className='text-sm font-medium'>({formatRupiah(stats.lost_revenue)})</span></p>
                </div>
              </div>
            </div>
          </div>

          { }
          <div className="w-full md:w-1/2 bg-white h-[300px] md:h-full rounded-lg shadow flex flex-col items-center text-white font-bold text-lg sm:text-2xl pt-3 overflow-hidden">
            <h3 className='text-black'>RECENT PROJECT</h3>
            <div className='w-full flex-1 overflow-y-auto px-2 pb-3 custom-scrollbar'>
              {recent.length === 0 ? (
                <p className='text-center text-gray-400 text-sm mt-6 font-normal'>Belum ada project</p>
              ) : recent.map((p) => (
                <div key={p.id} className='flex mt-3 w-11/12 mx-auto bg-slate-700 rounded-lg p-3 items-center justify-between'>
                  <span className='font-bold text-sm truncate mr-2'>{p.project_name}</span>
                  <span className={`text-[10px] ${STATUS_BG[p.status] || 'bg-gray-500'} px-2 py-1 rounded-full shrink-0`}>{p.status}</span>
                </div>
              ))}
            </div>
            <Link to='/project' className='text-sm font-bold text-gray-900 hover:text-gray-700 transition pb-3 pt-2'>
              Lihat selengkapnya →
            </Link>
          </div>
        </div>

        { }
        <div className='h-[380px]'>
          <SalesLeaderboard authFetch={authFetch} isAdmin={false} />
        </div>
      </div>
    </div>
  )
}


const AdminDashboard = ({ authFetch }) => {
  const [stats, setStats] = useState(null)
  const [salesPerf, setSalesPerf] = useState([])
  const [recent, setRecent] = useState([])
  const [stale, setStale] = useState([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const load = async () => {
      try {
        const [statsRes, perfRes, recentRes, staleRes] = await Promise.all([
          authFetch(`${API_BASE}/dashboard/stats`),
          authFetch(`${API_BASE}/dashboard/sales-performance`),
          authFetch(`${API_BASE}/dashboard/recent-projects?limit=5`),
          authFetch(`${API_BASE}/dashboard/stale-projects`),
        ])
        const [statsD, perfD, recentD, staleD] = await Promise.all([
          statsRes.json(), perfRes.json(), recentRes.json(), staleRes.json()
        ])
        if (statsD.success) setStats(statsD.data)
        if (perfD.success) setSalesPerf(perfD.data)
        if (recentD.success) setRecent(recentD.data)
        if (staleD.success) setStale(staleD.data)
      } catch (err) {
        console.error('Admin dashboard error:', err)
      } finally {
        setLoading(false)
      }
    }
    load()
  }, [authFetch])

  if (loading || !stats) {
    return (
      <div className="flex-1 flex items-center justify-center text-gray-400">
        <div className="animate-spin w-8 h-8 border-3 border-stone-300 border-t-stone-600 rounded-full" />
      </div>
    )
  }

  const pieData = ['PROSPECT', 'NEAREST', 'ONGOING', 'LOST', 'DONE']
    .filter(s => stats[s] > 0)
    .map(s => ({ name: s, value: stats[s], color: STATUS_COLORS[s] }))

  return (
    <div className='flex-1 overflow-y-auto custom-scrollbar min-h-0 pr-1'>
      <div className='flex flex-col gap-4 sm:gap-5'>
        { }
        <div className='grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3'>
          <div className='bg-white rounded-lg shadow p-3 sm:p-4 flex items-center gap-3'>
            <div className='w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center shrink-0'>
              <FiActivity className='text-slate-600' size={20} />
            </div>
            <div>
              <p className='text-2xl font-bold text-gray-800'>{stats.total}</p>
              <p className='text-[10px] text-gray-400 uppercase tracking-wide'>Total Project</p>
            </div>
          </div>
          <div className='bg-white rounded-lg shadow p-3 sm:p-4 flex items-center gap-3'>
            <div className='w-10 h-10 rounded-xl bg-green-50 flex items-center justify-center shrink-0'>
              <FiCheckCircle className='text-green-500' size={20} />
            </div>
            <div>
              <p className='text-2xl font-bold text-green-600'>{stats.DONE}</p>
              <p className='text-[10px] text-gray-400 uppercase tracking-wide'>Done</p>
              <p className='text-[10px] text-green-500 font-medium mt-0.5'>{formatRupiah(stats.done_revenue)}</p>
            </div>
          </div>
          <div className='bg-white rounded-lg shadow p-3 sm:p-4 flex items-center gap-3'>
            <div className='w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center shrink-0'>
              <FiXCircle className='text-red-500' size={20} />
            </div>
            <div>
              <p className='text-2xl font-bold text-red-600'>{stats.LOST}</p>
              <p className='text-[10px] text-gray-400 uppercase tracking-wide'>Lost</p>
              <p className='text-[10px] text-red-400 font-medium mt-0.5'>{formatRupiah(stats.lost_revenue)}</p>
            </div>
          </div>
          <div className='bg-white rounded-lg shadow p-3 sm:p-4 flex items-center gap-3'>
            <div className='w-10 h-10 rounded-xl bg-purple-50 flex items-center justify-center shrink-0'>
              <FiClock className='text-purple-500' size={20} />
            </div>
            <div>
              <p className='text-2xl font-bold text-purple-600'>{stats.ONGOING}</p>
              <p className='text-[10px] text-gray-400 uppercase tracking-wide'>Ongoing</p>
            </div>
          </div>
          <div className='bg-white rounded-lg shadow p-3 sm:p-4 flex items-center gap-3'>
            <div className='w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center shrink-0'>
              <FiDollarSign className='text-emerald-500' size={20} />
            </div>
            <div>
              <p className='text-xl font-bold text-emerald-600'>{formatRupiah(stats.total_revenue)}</p>
              <p className='text-[10px] text-gray-400 uppercase tracking-wide'>Revenue</p>
            </div>
          </div>
          <div className='bg-white rounded-lg shadow p-3 sm:p-4 flex items-center gap-3'>
            <div className='w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center shrink-0'>
              <FiAlertTriangle className='text-amber-500' size={20} />
            </div>
            <div>
              <p className='text-2xl font-bold text-amber-600'>{stats.mangkrak_count}</p>
              <p className='text-[10px] text-gray-400 uppercase tracking-wide'>Mangkrak</p>
            </div>
          </div>
        </div>

        { }
        <div className='flex flex-col lg:flex-row gap-4'>
          { }
          <div className='w-full lg:w-2/5 bg-white rounded-lg shadow p-4'>
            <h3 className='font-bold text-sm text-gray-700 mb-3'>Status Semua Project</h3>
            {pieData.length > 0 ? (
              <ResponsiveContainer width='100%' height={220}>
                <PieChart>
                  <Pie data={pieData} cx='40%' cy='50%' outerRadius={85} dataKey='value' stroke='none'>
                    {pieData.map((entry, index) => (
                      <Cell key={index} fill={entry.color} />
                    ))}
                  </Pie>
                  <Tooltip formatter={(value, name) => [value, name]} />
                  <Legend layout='vertical' align='right' verticalAlign='middle' content={renderLegend} />
                </PieChart>
              </ResponsiveContainer>
            ) : (
              <div className='h-[220px] flex items-center justify-center text-gray-400 text-sm'>Belum ada data</div>
            )}
          </div>

          { }
          <div className='w-full lg:w-3/5'>
            <TradingChart authFetch={authFetch} />
          </div>
        </div>

        { }
        <div className='flex flex-col lg:flex-row gap-4'>
          <div className='w-full lg:w-1/2 grid grid-cols-2 sm:grid-cols-3 gap-3'>
            <StatusCard title='PROSPECT' value={stats.PROSPECT} bgColor='bg-amber-500'/>
            <StatusCard title='NEAREST' value={stats.NEAREST} bgColor='bg-blue-500' />
            <StatusCard title='ONGOING' value={stats.ONGOING} bgColor='bg-purple-500' />
            <StatusCard title='LOST' value={stats.LOST} bgColor='bg-red-500' />
            <StatusCard title='DONE' value={stats.DONE} bgColor='bg-green-500' />
            <div className='bg-indigo-500 rounded-lg p-3 sm:p-4 flex flex-col items-center justify-center text-white cursor-pointer transition-all duration-200 hover:scale-105 hover:shadow-lg hover:brightness-110'>
              <span className='text-xs sm:text-sm font-semibold tracking-wide'>CLOSING RATE</span>
              <span className='text-2xl sm:text-3xl font-bold'>{stats.closing_rate}%</span>
            </div>
          </div>

          { }
          <div className='w-full lg:w-1/2 bg-white rounded-lg shadow p-4'>
            <h3 className='font-bold text-sm text-gray-700 mb-3'>Performa Sales</h3>
            <div className='overflow-x-auto'>
              <table className='w-full text-sm'>
                <thead>
                  <tr className='text-gray-400 text-xs border-b border-gray-100'>
                    <th className='text-left pb-2 font-medium'>Sales</th>
                    <th className='text-center pb-2 font-medium'>Project</th>
                    <th className='text-center pb-2 font-medium'>Done</th>
                    <th className='text-center pb-2 font-medium'>Lost</th>
                    <th className='text-right pb-2 font-medium'>Revenue</th>
                  </tr>
                </thead>
                <tbody>
                  {salesPerf.length === 0 ? (
                    <tr><td colSpan={5} className='text-center text-gray-400 py-4 text-xs'>Belum ada data</td></tr>
                  ) : salesPerf.map((s, i) => (
                    <tr key={i} className='border-b border-gray-50 last:border-0'>
                      <td className='py-2.5'>
                        <div className='flex items-center gap-2'>
                          <div className='w-7 h-7 rounded-lg bg-teal-100 flex items-center justify-center text-teal-600 font-bold text-xs'>
                            {s.name.split(' ').map(n => n[0]).join('').slice(0, 2)}
                          </div>
                          <span className='text-gray-700 font-medium text-xs'>{s.name}</span>
                        </div>
                      </td>
                      <td className='text-center text-gray-600 py-2.5'>{s.total_projects}</td>
                      <td className='text-center py-2.5'>
                        <span className='text-green-600 font-semibold'>{s.done}</span>
                      </td>
                      <td className='text-center py-2.5'>
                        <span className='text-red-500 font-semibold'>{s.lost}</span>
                      </td>
                      <td className='text-right text-gray-700 font-medium py-2.5 text-xs'>{formatRupiah(s.revenue)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>

        { }
        <div className='flex flex-col md:flex-row gap-4'>
          { }
          <div className="w-full md:w-1/3 h-[380px]">
            <SalesLeaderboard authFetch={authFetch} isAdmin={true} />
          </div>

          { }
          <div className="w-full md:w-1/3 bg-slate-600 h-[380px] rounded-lg flex flex-col items-center text-white font-bold text-lg pt-3 overflow-hidden">
            <h3>RECENT PROJECT</h3>
            <div className='w-full flex-1 overflow-y-auto px-2 pb-3 custom-scrollbar'>
              {recent.length === 0 ? (
                <p className='text-center text-gray-400 text-sm mt-6 font-normal'>Belum ada project</p>
              ) : recent.map((p) => (
                <div key={p.id} className='flex mt-3 w-11/12 mx-auto bg-slate-700 rounded-lg p-3 items-center justify-between'>
                  <div className='min-w-0 mr-2'>
                    <span className='font-bold text-sm block truncate'>{p.project_name}</span>
                    <span className='text-[10px] text-gray-400 font-normal'>{p.sales_name || '-'}</span>
                  </div>
                  <span className={`text-[10px] ${STATUS_BG[p.status] || 'bg-gray-500'} px-2 py-1 rounded-full shrink-0`}>{p.status}</span>
                </div>
              ))}
            </div>
            <Link to='/project' className='text-sm font-normal text-gray-300 hover:text-white transition pb-3 pt-2'>
              Lihat selengkapnya →
            </Link>
          </div>

          { }
          <div className="w-full md:w-1/3 bg-slate-600 h-[380px] rounded-lg flex flex-col items-center text-white font-bold text-lg pt-3 overflow-hidden">
            <h3 className='flex items-center gap-2'>
              <FiAlertTriangle className='text-amber-400' size={18} />
              MANGKRAK
            </h3>
            <h4 className='text-sm text-gray-300 font-normal'>Tanpa update &gt; 30 hari</h4>
            <div className='w-full flex-1 overflow-y-auto px-2 pb-3 custom-scrollbar'>
              {stale.length === 0 ? (
                <p className='text-center text-green-400 text-sm mt-6 font-semibold'>Tidak ada project yang belum di update</p>
              ) : stale.map((p) => (
                <div key={p.id} className='flex mt-3 w-11/12 mx-auto bg-slate-700 rounded-lg p-3 items-center justify-between border-l-2 border-amber-400'>
                  <div className='min-w-0 mr-2'>
                    <span className='font-bold text-sm block truncate'>{p.project_name}</span>
                    <span className='text-[10px] text-gray-400 font-normal'>{p.sales_name || '-'}</span>
                  </div>
                  <span className='text-xs bg-amber-500/30 text-amber-300 px-2 py-1 rounded-full font-medium shrink-0'>{p.days_stale} hari</span>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}


const Dashboard = () => {
  const { isAdmin, authFetch } = useAuth()

  return (
    <div className='w-full h-full p-2 sm:p-4 lg:p-6 flex flex-col'>
      {isAdmin ? <AdminDashboard authFetch={authFetch} /> : <SalesDashboard authFetch={authFetch} />}
    </div>
  )
}

export default Dashboard
