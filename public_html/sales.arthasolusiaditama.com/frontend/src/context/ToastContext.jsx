import React, { createContext, useContext, useState, useCallback, useRef } from 'react'
import { FiCheckCircle, FiAlertCircle, FiInfo, FiX, FiAlertTriangle } from 'react-icons/fi'

const ToastContext = createContext(null)

export const useToast = () => {
  const ctx = useContext(ToastContext)
  if (!ctx) throw new Error('useToast must be used within ToastProvider')
  return ctx
}

const toastConfig = {
  success: { icon: FiCheckCircle, bg: 'bg-green-50 border-green-300', text: 'text-green-800', iconColor: 'text-green-500', bar: 'bg-green-500' },
  error: { icon: FiAlertCircle, bg: 'bg-red-50 border-red-300', text: 'text-red-800', iconColor: 'text-red-500', bar: 'bg-red-500' },
  warning: { icon: FiAlertTriangle, bg: 'bg-yellow-50 border-yellow-300', text: 'text-yellow-800', iconColor: 'text-yellow-500', bar: 'bg-yellow-500' },
  info: { icon: FiInfo, bg: 'bg-blue-50 border-blue-300', text: 'text-blue-800', iconColor: 'text-blue-500', bar: 'bg-blue-500' },
}

const Toast = ({ toast, onRemove }) => {
  const conf = toastConfig[toast.type] || toastConfig.info
  const Icon = conf.icon

  return (
    <div
      className={`flex items-start gap-3 p-3 pr-10 rounded-lg border shadow-lg relative overflow-hidden ${conf.bg} animate-slide-in-right`}
      style={{ minWidth: 280, maxWidth: 400 }}
    >
      <Icon size={18} className={`${conf.iconColor} shrink-0 mt-0.5`} />
      <div className="flex-1 min-w-0">
        {toast.title && <p className={`text-sm font-semibold ${conf.text}`}>{toast.title}</p>}
        <p className={`text-sm ${conf.text} ${toast.title ? 'mt-0.5' : ''}`}>{toast.message}</p>
      </div>
      <button
        onClick={() => onRemove(toast.id)}
        className="absolute top-2 right-2 p-1 rounded hover:bg-black/5 transition"
      >
        <FiX size={14} className="text-gray-400" />
      </button>
      { }
      <div className="absolute bottom-0 left-0 right-0 h-1">
        <div
          className={`h-full ${conf.bar} animate-toast-progress`}
          style={{ animationDuration: `${toast.duration || 4000}ms` }}
        />
      </div>
    </div>
  )
}

export const ToastProvider = ({ children }) => {
  const [toasts, setToasts] = useState([])
  const idRef = useRef(0)

  const removeToast = useCallback((id) => {
    setToasts(prev => prev.filter(t => t.id !== id))
  }, [])

  const addToast = useCallback((type, message, title, duration = 4000) => {
    const id = ++idRef.current
    setToasts(prev => [...prev, { id, type, message, title, duration }])
    setTimeout(() => removeToast(id), duration)
    return id
  }, [removeToast])

  const [modal, setModal] = useState({ show: false, type: 'success', message: '', title: '' })

  const hideModal = useCallback(() => {
    setModal(m => ({ ...m, show: false }))
  }, [])
  const showModal = useCallback((type, message, title) => {
    setModal({ show: true, type, message, title })
    setTimeout(hideModal, 3000)
  }, [hideModal])

  const toast = {
    success: (message, title) => showModal('success', message, title),
    error: (message, title) => showModal('error', message, title),
    warning: (message, title) => showModal('warning', message, title),
    info: (message, title) => showModal('info', message, title),
    
    smallSuccess: (message, title) => addToast('success', message, title),
    smallError: (message, title) => addToast('error', message, title, 6000),
    smallWarning: (message, title) => addToast('warning', message, title, 5000),
    smallInfo: (message, title) => addToast('info', message, title),
    
    successModal: (message, title) => showModal('success', message, title),
    errorModal: (message, title) => showModal('error', message, title),
    warningModal: (message, title) => showModal('warning', message, title),
    infoModal: (message, title) => showModal('info', message, title),
  }

  return (
    <ToastContext.Provider value={toast}>
      {children}
      { }
      <div className="fixed top-4 right-4 z-[9999] flex flex-col gap-2 pointer-events-none">
        {toasts.map(t => (
          <div key={t.id} className="pointer-events-auto">
            <Toast toast={t} onRemove={removeToast} />
          </div>
        ))}
      </div>

      { }
      {modal.show && (
        <div className="fixed inset-0 bg-black/40 z-[9998] flex items-center justify-center p-4">
          <div className="bg-white rounded-lg p-6 flex flex-col items-center max-w-sm text-center shadow-lg animate-modal-scale">
            { }
            <video autoPlay muted className="w-24 h-24 mb-4" key={modal.type}>
              <source src={modal.type === 'success' ? '/Checked.webm' : '/failed.webm'} type="video/webm" />
            </video>
            {modal.title && <p className="text-xl font-bold mb-2">{modal.title}</p>}
            <p className="text-gray-700">{modal.message}</p>
          </div>
        </div>
      )}
    </ToastContext.Provider>
  )
}

export default ToastContext
