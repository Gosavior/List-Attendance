import React from 'react'
import { IoTrash, IoRefresh, IoWarning } from 'react-icons/io5'

const formatDate = (iso) => {
  const d = new Date(iso)
  return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const RecycleBin = ({ recycle, onRestore, onPermanentDelete, onEmptyRecycle }) => {
  if (recycle.length === 0) {
    return (
      <div className="flex flex-col items-center justify-center py-16 text-gray-400">
        <IoTrash size={48} className="mb-3 opacity-40" />
        <p className="text-sm">Recycle bin kosong</p>
      </div>
    )
  }

  return (
    <div>
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center gap-2 text-amber-600">
          <IoWarning size={18} />
          <span className="text-sm">Item di recycle bin akan terhapus permanen jika dihapus</span>
        </div>
        <button
          onClick={onEmptyRecycle}
          className="text-xs text-red-500 hover:text-red-700 border border-red-300 hover:border-red-500 px-3 py-1.5 rounded-lg transition-colors"
        >
          Kosongkan Semua
        </button>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        {recycle.map(item => (
          <div key={item.id} className="bg-white border border-red-100 rounded-xl p-4 shadow-sm flex flex-col gap-2 opacity-80">
            <div className="flex items-center gap-2 min-w-0">
              <IoTrash className="text-red-300 shrink-0" size={18} />
              <span className="font-medium text-gray-600 text-sm truncate">{item.name}</span>
            </div>
            <div className="flex gap-1 text-xs text-gray-400 flex-col">
              <span>Dibuat: {formatDate(item.createdAt)}</span>
              {item.deletedAt && <span>Dihapus: {formatDate(item.deletedAt)}</span>}
            </div>
            <span className={`self-start text-xs font-bold px-2 py-0.5 rounded-full ${item.category === 'ASA' ? 'bg-blue-100 text-blue-600' : 'bg-green-100 text-green-600'}`}>
              {item.category}
            </span>
            <div className="flex gap-2 mt-1">
              <button
                onClick={() => onRestore(item.id)}
                className="flex-1 flex items-center justify-center gap-1 text-xs bg-green-500 hover:bg-green-600 text-white py-1.5 rounded-lg transition-colors"
              >
                <IoRefresh size={14} /> Restore
              </button>
              <button
                onClick={() => onPermanentDelete(item.id)}
                className="flex-1 flex items-center justify-center gap-1 text-xs bg-red-500 hover:bg-red-600 text-white py-1.5 rounded-lg transition-colors"
              >
                <IoTrash size={14} /> Hapus
              </button>
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}

export default RecycleBin
