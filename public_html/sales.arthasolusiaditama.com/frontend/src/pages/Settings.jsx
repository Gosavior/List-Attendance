import React, { useState, useEffect, useCallback } from 'react'
import { FiBell, FiGlobe, FiLock, FiToggleLeft, FiToggleRight, FiSave, FiInfo, FiShield, FiCheck, FiX } from 'react-icons/fi'
import { useAuth } from '../context/AuthContext'

const API_BASE = import.meta.env.VITE_API_BASE || '/api'

const Settings = () => {
  const { authFetch, user } = useAuth()
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [saveMsg, setSaveMsg] = useState('')

  const [notifications, setNotifications] = useState({
    projectReminder: true,
    materialRequest: true,
    salesUpdate: true,
  })
  const [language, setLanguage] = useState('id')
  const [theme, setTheme] = useState('light')

  
  const [showPwModal, setShowPwModal] = useState(false)
  const [pwForm, setPwForm] = useState({ currentPassword: '', newPassword: '', confirmPassword: '' })
  const [pwLoading, setPwLoading] = useState(false)
  const [pwMsg, setPwMsg] = useState({ type: '', text: '' })

  const fetchSettings = useCallback(async () => {
    try {
      const res = await authFetch(`${API_BASE}/settings`)
      const data = await res.json()
      if (data.success) {
        setNotifications(data.data.notifications)
        setLanguage(data.data.language)
        setTheme(data.data.theme)
      }
    } catch (err) { console.error(err) }
    finally { setLoading(false) }
  }, [authFetch])

  useEffect(() => { fetchSettings() }, [fetchSettings])

  const handleSave = async () => {
    setSaving(true)
    setSaveMsg('')
    try {
      const res = await authFetch(`${API_BASE}/settings`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ language, theme, notifications }),
      })
      const data = await res.json()
      setSaveMsg(data.success ? 'Pengaturan berhasil disimpan!' : data.message)
      if (data.success) setTimeout(() => setSaveMsg(''), 3000)
    } catch (err) { setSaveMsg('Gagal menyimpan pengaturan.') }
    finally { setSaving(false) }
  }

  const handleChangePassword = async () => {
    if (!pwForm.currentPassword || !pwForm.newPassword) {
      setPwMsg({ type: 'error', text: 'Semua field wajib diisi.' })
      return
    }
    if (pwForm.newPassword.length < 6) {
      setPwMsg({ type: 'error', text: 'Password baru minimal 6 karakter.' })
      return
    }
    if (pwForm.newPassword !== pwForm.confirmPassword) {
      setPwMsg({ type: 'error', text: 'Konfirmasi password tidak cocok.' })
      return
    }
    setPwLoading(true)
    setPwMsg({ type: '', text: '' })
    try {
      const res = await authFetch(`${API_BASE}/auth/change-password`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ currentPassword: pwForm.currentPassword, newPassword: pwForm.newPassword }),
      })
      const data = await res.json()
      if (data.success) {
        setPwMsg({ type: 'success', text: 'Password berhasil diubah!' })
        setTimeout(() => { setShowPwModal(false); setPwForm({ currentPassword: '', newPassword: '', confirmPassword: '' }); setPwMsg({ type: '', text: '' }) }, 1500)
      } else {
        setPwMsg({ type: 'error', text: data.message })
      }
    } catch (err) { setPwMsg({ type: 'error', text: 'Gagal mengubah password.' }) }
    finally { setPwLoading(false) }
  }

  const Toggle = ({ enabled, onChange }) => (
    <button onClick={onChange} className="transition">
      {enabled ? (
        <FiToggleRight size={28} className="text-indigo-600" />
      ) : (
        <FiToggleLeft size={28} className="text-gray-400" />
      )}
    </button>
  )

  const SettingRow = ({ icon: Icon, iconColor, title, description, children }) => (
    <div className="flex items-center justify-between gap-4 py-3 border-b border-gray-100 last:border-0">
      <div className="flex items-start gap-3 min-w-0">
        <div className={`w-8 h-8 rounded-lg flex items-center justify-center shrink-0 ${iconColor}`}>
          <Icon size={15} />
        </div>
        <div className="min-w-0">
          <p className="text-sm font-medium text-gray-800">{title}</p>
          <p className="text-[11px] text-gray-500 mt-0.5">{description}</p>
        </div>
      </div>
      <div className="shrink-0">{children}</div>
    </div>
  )

  if (loading) {
    return (
      <div className="w-full h-full bg-white rounded-xl p-6 flex items-center justify-center">
        <p className="text-sm text-gray-400 animate-pulse">Memuat pengaturan...</p>
      </div>
    )
  }

  return (
    <div className="w-full h-full bg-white rounded-xl p-3 sm:p-6 text-gray-800 flex flex-col overflow-hidden">
      <div className="flex items-center justify-between mb-5 shrink-0">
        <div>
          <h2 className="font-bold text-xl sm:text-2xl text-gray-900">SETTINGS</h2>
          <p className="text-sm text-gray-500 mt-0.5">Pengaturan website dan preferensi</p>
        </div>
        <div className="flex items-center gap-3">
          {saveMsg && (
            <span className="text-xs text-green-600 font-medium flex items-center gap-1">
              <FiCheck size={14} /> {saveMsg}
            </span>
          )}
          <button
            onClick={handleSave}
            disabled={saving}
            className="flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-medium transition disabled:opacity-50"
          >
            <FiSave size={14} /> {saving ? 'Menyimpan...' : 'Simpan'}
          </button>
        </div>
      </div>

      <div className="flex-1 overflow-y-auto custom-scrollbar min-h-0 pr-1 space-y-4">
        { }
        <div className="bg-gray-50 border border-gray-200 rounded-xl p-4 sm:p-5">
          <div className="flex items-center gap-2 mb-4">
            <div className="w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center">
              <FiBell className="text-amber-600" size={16} />
            </div>
            <div>
              <h3 className="font-semibold text-sm text-gray-900">Notifikasi</h3>
              <p className="text-[10px] text-gray-500">Atur preferensi notifikasi Anda</p>
            </div>
          </div>

          <div className="px-1">
            <SettingRow
              icon={FiBell}
              iconColor="bg-orange-100 text-orange-600"
              title="Project Reminder"
              description="Ingatkan jika ada project belum di-follow-up"
            >
              <Toggle
                enabled={notifications.projectReminder}
                onChange={() => setNotifications(n => ({ ...n, projectReminder: !n.projectReminder }))}
              />
            </SettingRow>

            <SettingRow
              icon={FiInfo}
              iconColor="bg-cyan-100 text-cyan-600"
              title="Material Request"
              description="Notifikasi saat ada permintaan material baru"
            >
              <Toggle
                enabled={notifications.materialRequest}
                onChange={() => setNotifications(n => ({ ...n, materialRequest: !n.materialRequest }))}
              />
            </SettingRow>

            <SettingRow
              icon={FiInfo}
              iconColor="bg-green-100 text-green-600"
              title="Sales Update"
              description="Notifikasi saat sales mengupdate project"
            >
              <Toggle
                enabled={notifications.salesUpdate}
                onChange={() => setNotifications(n => ({ ...n, salesUpdate: !n.salesUpdate }))}
              />
            </SettingRow>
          </div>
        </div>

        { }
        <div className="bg-gray-50 border border-gray-200 rounded-xl p-4 sm:p-5">
          <div className="flex items-center gap-2 mb-4">
            <div className="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center">
              <FiShield className="text-red-600" size={16} />
            </div>
            <div>
              <h3 className="font-semibold text-sm text-gray-900">Keamanan</h3>
              <p className="text-[10px] text-gray-500">Pengaturan keamanan akun</p>
            </div>
          </div>

          <div className="px-1">
            <div className="py-3">
              <div className="flex items-start gap-3">
                <div className="w-8 h-8 rounded-lg bg-orange-100 flex items-center justify-center shrink-0 text-orange-600">
                  <FiLock size={15} />
                </div>
                <div className="flex-1">
                  <p className="text-sm font-medium text-gray-800">Ganti Password</p>
                  <p className="text-[11px] text-gray-500 mt-0.5">Ubah password secara berkala untuk keamanan</p>
                  <button
                    onClick={() => { setShowPwModal(true); setPwMsg({ type: '', text: '' }); setPwForm({ currentPassword: '', newPassword: '', confirmPassword: '' }) }}
                    className="mt-2 px-3 py-1.5 text-xs rounded-lg bg-gray-200 hover:bg-gray-300 text-gray-700 transition font-medium"
                  >
                    Ubah Password
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        { }
        <div className="bg-gray-50 border border-gray-200 rounded-xl p-4 sm:p-5">
          <div className="flex items-center gap-2 mb-4">
            <div className="w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center">
              <FiGlobe className="text-indigo-600" size={16} />
            </div>
            <div>
              <h3 className="font-semibold text-sm text-gray-900">Tampilan</h3>
              <p className="text-[10px] text-gray-500">Kustomisasi tampilan website</p>
            </div>
          </div>

          <div className="px-1">
            <SettingRow
              icon={FiGlobe}
              iconColor="bg-teal-100 text-teal-600"
              title="Bahasa"
              description="Pilih bahasa tampilan website"
            >
              <select
                value={language}
                onChange={(e) => setLanguage(e.target.value)}
                className="bg-white border border-gray-200 rounded-lg px-3 py-1.5 text-xs text-gray-800 focus:outline-none focus:border-indigo-400 focus:ring-1 focus:ring-indigo-400"
              >
                <option value="id">Indonesia</option>
                <option value="en">English</option>
              </select>
            </SettingRow>
          </div>
        </div>

        { }
        <div className="bg-gray-50 border border-gray-200 rounded-xl p-4 sm:p-5">
          <div className="flex items-center gap-2 mb-3">
            <div className="w-8 h-8 rounded-lg bg-slate-200 flex items-center justify-center">
              <FiInfo className="text-slate-600" size={16} />
            </div>
            <div>
              <h3 className="font-semibold text-sm text-gray-900">Tentang</h3>
              <p className="text-[10px] text-gray-500">Informasi aplikasi</p>
            </div>
          </div>
          <div className="px-1 text-xs text-gray-500 space-y-1.5">
            <p><span className="text-gray-700 font-medium">Sales Management System</span></p>
            <p>PT Artha Solusi Aditama</p>
            <p>Versi 1.0.0</p>
          </div>
        </div>
      </div>

      { }
      {showPwModal && (
        <div className="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" onClick={() => setShowPwModal(false)}>
          <div className="bg-white border border-gray-200 rounded-xl w-full max-w-sm shadow-xl" onClick={(e) => e.stopPropagation()}>
            <div className="flex items-center justify-between p-4 border-b border-gray-200">
              <h3 className="font-bold text-lg text-gray-900">Ubah Password</h3>
              <button onClick={() => setShowPwModal(false)} className="p-1 hover:bg-gray-100 rounded-lg transition text-gray-500">
                <FiX size={18} />
              </button>
            </div>
            <div className="p-4 space-y-4">
              {pwMsg.text && (
                <p className={`text-sm p-2 rounded-lg ${pwMsg.type === 'error' ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-600'}`}>
                  {pwMsg.text}
                </p>
              )}
              <div>
                <label className="block text-xs text-gray-500 mb-1.5">Password Lama</label>
                <input
                  type="password"
                  value={pwForm.currentPassword}
                  onChange={(e) => setPwForm(f => ({ ...f, currentPassword: e.target.value }))}
                  placeholder="Masukkan password lama"
                  className="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-indigo-400 focus:ring-1 focus:ring-indigo-400"
                />
              </div>
              <div>
                <label className="block text-xs text-gray-500 mb-1.5">Password Baru</label>
                <input
                  type="password"
                  value={pwForm.newPassword}
                  onChange={(e) => setPwForm(f => ({ ...f, newPassword: e.target.value }))}
                  placeholder="Minimal 6 karakter"
                  className="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-indigo-400 focus:ring-1 focus:ring-indigo-400"
                />
              </div>
              <div>
                <label className="block text-xs text-gray-500 mb-1.5">Konfirmasi Password Baru</label>
                <input
                  type="password"
                  value={pwForm.confirmPassword}
                  onChange={(e) => setPwForm(f => ({ ...f, confirmPassword: e.target.value }))}
                  placeholder="Ulangi password baru"
                  className="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-indigo-400 focus:ring-1 focus:ring-indigo-400"
                />
              </div>
            </div>
            <div className="flex items-center justify-end gap-2 p-4 border-t border-gray-200">
              <button onClick={() => setShowPwModal(false)} className="px-4 py-2 text-sm rounded-lg bg-gray-100 hover:bg-gray-200 transition text-gray-700">Batal</button>
              <button
                onClick={handleChangePassword}
                disabled={pwLoading}
                className="px-4 py-2 text-sm rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white transition font-medium disabled:opacity-50"
              >
                {pwLoading ? 'Menyimpan...' : 'Ubah Password'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

export default Settings
