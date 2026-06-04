import React, { useState, useEffect, useCallback } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { FiHome, FiChevronUp, FiUsers, FiTool, FiInbox, FiActivity, FiX, FiLogOut, FiPackage, FiArchive, FiUser, FiSettings, FiDatabase, FiClipboard } from 'react-icons/fi'
import { useAuth } from '../context/AuthContext'
import { useSocket } from '../context/SocketContext'
import { getAvatarUrl } from '../utils/avatar'

const API_BASE = import.meta.env.VITE_API_BASE || '/api'

function Sidebar({ isOpen, onClose }) {
  const { user, logout, authFetch } = useAuth()
  const { onNotification } = useSocket()
  const navigate = useNavigate()
  const [openProject, setOpenProject] = useState(false);
  const [openAccount, setOpenAccount] = useState(false);
  const [materialBadge, setMaterialBadge] = useState(0);
  const location = useLocation();

  const isActive = (path) => location.pathname === path;
  const isAdmin = user?.role === 'administrator' || user?.role === 'direktur';

  const fetchMaterialCount = useCallback(async () => {
    try {
      const res = await authFetch(`${API_BASE}/material-requests/stats/summary`)
      const data = await res.json()
      if (data.success) {
        setMaterialBadge((data.data.pending || 0) + (data.data.sales_approved || 0) + (data.data.admin_review || 0))
      }
    } catch (e) {}
  }, [authFetch])

  useEffect(() => { fetchMaterialCount() }, [fetchMaterialCount])
  useEffect(() => {
    const unsub = onNotification((data) => {
      if (data.type === 'material_request') fetchMaterialCount()
    })
    return unsub
  }, [onNotification, fetchMaterialCount])

  return (
    <>
      {isOpen && (
        <div className='fixed inset-0 bg-black/50 z-40 md:hidden' onClick={onClose} />
      )}
      <div className={`fixed top-0 left-0 h-full z-50 bg-slate-700 text-white flex flex-col w-56 transition-transform duration-300 md:static md:min-h-screen md:translate-x-0 md:shrink-0 ${isOpen ? 'translate-x-0' : '-translate-x-full'}`}>

      { }
      <div className='h-20 flex items-center gap-3 px-4 bg-slate-800'>
        
        <img src='/logo.png' alt='ASA' className='w-12 h-12 rounded-lg object-contain flex-shrink-0' />
        
        <div className='flex-1'>
          <h3 className='font-bold text-sm leading-tight'>Artha Solusi Aditama</h3>
          <p className='text-xs text-gray-300'>Sales System</p>
        </div>

        <button onClick={onClose} className='md:hidden p-1 hover:bg-slate-600 rounded'>
          <FiX size={20} />
        </button>
      </div>

      <nav className='flex-1 p-3 flex flex-col gap-2 overflow-y-auto'>
        <Link 
          to='/dashboard' 
          onClick={onClose}
          className={`flex items-center gap-3 px-3 py-2 rounded transition font-bold ${
            isActive('/dashboard') ? 'bg-slate-600' : 'hover:bg-slate-600'
          }`}
        >
          <FiHome size={18} /> Dashboard
        </Link>
        
        <Link 
          to='/sales-tracker' 
          onClick={onClose}
          className={`flex items-center gap-3 px-3 py-2 rounded transition font-bold ${
            isActive('/sales-tracker') ? 'bg-slate-600' : 'hover:bg-slate-600'
          }`}
        >
          <FiActivity size={18} /> Sales Tracker
        </Link>
        
        <Link 
          to='/inbox' 
          onClick={onClose}
          className={`flex items-center gap-3 px-3 py-2 rounded transition font-bold ${
            isActive('/inbox') ? 'bg-slate-600' : 'hover:bg-slate-600'
          }`}
        >
          <FiInbox size={18} /> Inbox
        </Link>



        <Link 
          to='/requestMaterial' 
          onClick={onClose}
          className={`flex items-center gap-3 px-3 py-2 rounded transition font-bold ${
            isActive('/requestMaterial') ? 'bg-slate-600' : 'hover:bg-slate-600'
          }`}
        >
          <FiPackage size={18} /> Request Material
          {materialBadge > 0 && (
            <span className="ml-auto min-w-[20px] h-5 px-1.5 flex items-center justify-center rounded-full bg-red-500 text-white text-[10px] font-bold animate-pulse">{materialBadge}</span>
          )}
        </Link>

        <Link 
          to='/stock' 
          onClick={onClose}
          className={`flex items-center gap-3 px-3 py-2 rounded transition font-bold ${
            isActive('/stock') ? 'bg-slate-600' : 'hover:bg-slate-600'
          }`}
        >
          <FiDatabase size={18} /> Stock Material
        </Link>

        {isAdmin && (
        <Link 
          to='/stock-check' 
          onClick={onClose}
          className={`flex items-center gap-3 px-3 py-2 rounded transition font-bold ${
            isActive('/stock-check') ? 'bg-slate-600' : 'hover:bg-slate-600'
          }`}
        >
          <FiClipboard size={18} /> Stock Opname
        </Link>
        )}
        
        
        <div>
          <div className='flex items-center rounded hover:bg-slate-600 transition'>
            <Link
              to='/project'
              onClick={onClose}
              className='flex items-center gap-3 px-3 py-2 flex-1 font-bold'
            >
              <FiTool size={18} />
              Project
            </Link>

            <button onClick={() => setOpenProject(!openProject)} className='px-2 py-2'>
              <FiChevronUp
                size={18}
                className={`bg-neutral-500 rounded-md transition-transform duration-300 ${openProject ? 'rotate-0' : 'rotate-180'}`}
              />
            </button>
          </div>
          
          <div
            className={`ml-8 flex flex-col gap-1 overflow-hidden transition-all duration-300 ${openProject ? 'max-h-80 mt-1' : 'max-h-0'}`}
          >
            <Link to='/project/qo' onClick={onClose} className='flex items-center gap-2 px-3 py-1 rounded hover:bg-slate-600 text-sm font-bold'>
              <FiArchive size={14} className='shrink-0' /> QO (Quotation Order)
            </Link>
            <Link to='/project/ao' onClick={onClose} className='flex items-center gap-2 px-3 py-1 rounded hover:bg-slate-600 text-sm font-bold'>
              <FiArchive size={14} className='shrink-0' /> AO (Acceptance Order)
            </Link>
            <Link to='/project/rab' onClick={onClose} className='flex items-center gap-2 px-3 py-1 rounded hover:bg-slate-600 text-sm font-bold'>
              <FiArchive size={14} className='shrink-0' /> RAB
            </Link>
            <Link to='/project/invoice' onClick={onClose} className='flex items-center gap-2 px-3 py-1 rounded hover:bg-slate-600 text-sm font-bold'>
              <FiArchive size={14} className='shrink-0' /> Invoice
            </Link>
            <Link to='/project/report' onClick={onClose} className='flex items-center gap-2 px-3 py-1 rounded hover:bg-slate-600 text-sm font-bold'>
              <FiArchive size={14} className='shrink-0' /> Report
            </Link>
          </div>
        </div>
        
        <div>
          <div className='flex items-center rounded hover:bg-slate-600 transition'>
            <Link
              to='/accounts'
              onClick={onClose}
              className='flex items-center gap-3 px-3 py-2 flex-1 font-bold'
            >
              <FiUsers size={18} />
              Accounts
            </Link>

            <button onClick={() => setOpenAccount(!openAccount)} className='px-2 py-2'>
              <FiChevronUp
                size={18}
                className={`bg-neutral-500 rounded-md transition-transform duration-300 ${openAccount ? 'rotate-0' : 'rotate-180'}`}
              />
            </button>
          </div>
          
          <div
            className={`ml-8 flex flex-col gap-1 overflow-hidden transition-all duration-300 ${openAccount ? 'max-h-40 mt-1' : 'max-h-0'}`}
          >
            <Link to='/profile' onClick={onClose} className='flex items-center gap-2 px-3 py-1 rounded hover:bg-slate-600 text-sm font-bold'>
              <FiUser size={14} className='shrink-0' /> Profile
            </Link>
            <Link to='/accounts/customers' onClick={onClose} className='flex items-center gap-2 px-3 py-1 rounded hover:bg-slate-600 text-sm font-bold'>
              <FiUsers size={14} className='shrink-0' /> Customers
            </Link>
            {isAdmin && (
            <Link to='/accounts/suppliers' onClick={onClose} className='flex items-center gap-2 px-3 py-1 rounded hover:bg-slate-600 text-sm font-bold'>
              <FiPackage size={14} className='shrink-0' /> Suppliers
            </Link>
            )}
            <Link to='/settings' onClick={onClose} className='flex items-center gap-2 px-3 py-1 rounded hover:bg-slate-600 text-sm font-bold'>
              <FiSettings size={14} className='shrink-0' /> Settings
            </Link>
          </div>
        </div>

        { }
        <div className='mt-auto pt-2 border-t border-slate-600'>
          {user && (
            <div className='px-3 py-2 mb-1 flex items-center gap-3'>
              {getAvatarUrl(user.avatar, user.id) ? (
                <img src={getAvatarUrl(user.avatar, user.id)} alt='Avatar' className='w-9 h-9 rounded-full object-cover shrink-0' />
              ) : (
                <div className='w-9 h-9 bg-orange-500 rounded-full flex items-center justify-center shrink-0 text-white font-bold text-sm'>
                  {user.name ? user.name.charAt(0).toUpperCase() : <FiUser size={14} />}
                </div>
              )}
              <div className='min-w-0'>
                <p className='text-xs text-gray-400 truncate'>{user.name || user.username}</p>
                <p className='text-[10px] text-gray-500 capitalize'>{user.role}</p>
              </div>
            </div>
          )}
          <button
            onClick={async () => {
              onClose()
              await logout()
            }}
            className='flex items-center gap-3 px-3 py-2 rounded transition font-bold text-red-300 hover:bg-red-500/20 w-full text-left'
          >
            <FiLogOut size={18} /> Logout
          </button>
        </div>
      </nav>
      </div>
    </>
  )
}

export default Sidebar