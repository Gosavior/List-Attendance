import React, { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'

const Login = () => {
  const { login, isLoggedIn, loading: authLoading } = useAuth()
  const navigate = useNavigate()

  const [currentRole, setCurrentRole] = useState('staff')
  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const [error, setError] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [showForgotModal, setShowForgotModal] = useState(false)

  useEffect(() => {
    if (!authLoading && isLoggedIn) {
      navigate('/dashboard', { replace: true })
    }
  }, [authLoading, isLoggedIn, navigate])

  const dividerLabels = {
    staff: 'Login Staff',
    sales: 'Login Sales',
    customer: 'Customer Portal',
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (!username.trim() || !password.trim()) {
      setError('Username dan password harus diisi')
      return
    }

    setError('')
    setSubmitting(true)

    try {
      const data = await login(username, password)

      if (data.success) {
        const userRole = data.user?.role
        if (currentRole === 'sales' && userRole !== 'sales' && userRole !== 'administrator' && userRole !== 'direktur') {
          setError('Bukan akun sales. Silakan login melalui form yang sesuai.')
          setSubmitting(false)
          return
        }
        if (currentRole === 'staff' && userRole === 'sales') {
          setError('Bukan akun staff. Silakan login melalui form yang sesuai.')
          setSubmitting(false)
          return
        }

        navigate('/dashboard', { replace: true })
      } else {
        setError(data.message || 'Username atau password salah')
      }
    } catch (err) {
      setError('Terjadi kesalahan sistem. Silakan coba lagi nanti.')
    } finally {
      setSubmitting(false)
    }
  }

  const switchRole = (role) => {
    if (role === 'customer') return
    setCurrentRole(role)
    setError('')
    setUsername('')
    setPassword('')
  }

  if (authLoading) {
    return (
      <div className="min-h-screen bg-gray-900 flex items-center justify-center">
        <div className="w-6 h-6 border-2 border-white/30 border-t-white rounded-full animate-spin" />
      </div>
    )
  }

  return (
    <>
      <style>{`
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');

        .login-page { font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif; }

        .scene-photo {
          position: absolute; inset: 0;
          background: url('/assets/images/hero-section.jpg') center/cover no-repeat;
          filter: blur(4px); transform: scale(1.05);
        }

        .login-card {
          background: rgba(255,255,255,0.07);
          backdrop-filter: blur(48px) saturate(1.2);
          -webkit-backdrop-filter: blur(48px) saturate(1.2);
          border: 1px solid rgba(255,255,255,0.08);
          box-shadow: 0 0 0 1px rgba(255,255,255,0.04) inset, 0 20px 50px -15px rgba(0,0,0,0.45);
        }

        .login-input {
          background: rgba(255,255,255,0.05);
          border: 1.5px solid rgba(255,255,255,0.08);
          transition: border-color 0.3s, background 0.3s, box-shadow 0.3s;
        }
        .login-input:hover {
          border-color: rgba(255,255,255,0.14);
          background: rgba(255,255,255,0.06);
        }
        .login-input::placeholder { color: rgba(255,255,255,0.2); }
        .login-input.accent-blue:focus {
          border-color: #3b82f6;
          background: rgba(255,255,255,0.07);
          box-shadow: 0 0 0 3px rgba(59,130,246,0.12);
        }
        .login-input.accent-amber:focus {
          border-color: #d97706;
          background: rgba(255,255,255,0.07);
          box-shadow: 0 0 0 3px rgba(245,158,11,0.15);
        }

        @keyframes staggerUp {
          from { opacity: 0; transform: translateY(12px); }
          to { opacity: 1; transform: translateY(0); }
        }
        .stagger { animation: staggerUp 0.55s cubic-bezier(0.4,0,0.2,1) forwards; opacity: 0; }
        .stagger-1 { animation-delay: 0.06s; }
        .stagger-2 { animation-delay: 0.12s; }
        .stagger-3 { animation-delay: 0.18s; }
        .stagger-4 { animation-delay: 0.24s; }
        .stagger-5 { animation-delay: 0.30s; }
        .stagger-6 { animation-delay: 0.36s; }

        @keyframes errorIn {
          from { opacity: 0; transform: translateY(-6px); }
          to { opacity: 1; transform: translateY(0); }
        }
        .error-animate { animation: errorIn 0.4s cubic-bezier(0.4,0,0.2,1); }

        @keyframes spin { to { transform: rotate(360deg); } }
        .login-spinner { animation: spin 0.7s linear infinite; }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes modalIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
        .modal-fade { animation: fadeIn 0.3s cubic-bezier(0.4,0,0.2,1); }
        .modal-slide { animation: modalIn 0.35s cubic-bezier(0.4,0,0.2,1); }
      `}</style>

      <div className="login-page min-h-screen bg-gray-900 overflow-x-hidden">
        { }
        <div className="fixed inset-0 z-0 overflow-hidden">
          <div className="scene-photo" />
          <div className="absolute inset-0 bg-slate-900/65" />
        </div>

        { }
        <div className="relative z-10 min-h-screen flex flex-col items-center justify-center px-4 py-6">
          <div className="login-card w-full max-w-[440px] rounded-3xl overflow-hidden">
            <div className="p-7 sm:p-9">

              { }
              <div className="text-center mb-7 stagger stagger-1">
                <img
                  src="/assets/images/logo.png"
                  alt="PT. Artha Solusi Aditama"
                  className="w-[72px] h-[72px] object-contain mx-auto mb-4 rounded-2xl drop-shadow-[0_4px_12px_rgba(0,0,0,0.3)]"
                  onError={(e) => { e.target.style.display = 'none' }}
                />
                <h1 className="text-xl font-bold text-white tracking-tight">PT. Artha Solusi Aditama</h1>
                <p className="text-[13px] text-white/40 mt-1">Masuk ke akun Anda untuk melanjutkan</p>
              </div>

              { }
              <div className="grid grid-cols-3 gap-2.5 mb-7 stagger stagger-2">
                { }
                <button
                  type="button"
                  onClick={() => switchRole('staff')}
                  className={`flex flex-col items-center gap-2 py-3.5 px-2 rounded-2xl border-[1.5px] transition-all duration-300 cursor-pointer select-none ${
                    currentRole === 'staff'
                      ? 'border-blue-500/35 bg-blue-500/10 shadow-[0_2px_12px_-3px_rgba(59,130,246,0.15)]'
                      : 'border-white/[0.07] bg-white/[0.03] hover:border-white/[0.12] hover:bg-white/[0.05]'
                  }`}
                >
                  <div className={`w-10 h-10 rounded-xl flex items-center justify-center transition-all duration-300 ${
                    currentRole === 'staff'
                      ? 'bg-gradient-to-br from-blue-500 to-blue-600 shadow-[0_3px_10px_-2px_rgba(37,99,235,0.4)]'
                      : 'bg-white/[0.06]'
                  }`}>
                    <svg className={`w-5 h-5 transition-colors duration-300 ${currentRole === 'staff' ? 'text-white' : 'text-white/50'}`} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                  </div>
                  <span className={`text-xs font-semibold tracking-wide transition-colors duration-300 ${currentRole === 'staff' ? 'text-white/90' : 'text-white/50'}`}>Staff</span>
                </button>

                { }
                <button
                  type="button"
                  onClick={() => switchRole('sales')}
                  className={`flex flex-col items-center gap-2 py-3.5 px-2 rounded-2xl border-[1.5px] transition-all duration-300 cursor-pointer select-none ${
                    currentRole === 'sales'
                      ? 'border-amber-600/35 bg-amber-600/10 shadow-[0_2px_12px_-3px_rgba(217,119,6,0.15)]'
                      : 'border-white/[0.07] bg-white/[0.03] hover:border-white/[0.12] hover:bg-white/[0.05]'
                  }`}
                >
                  <div className={`w-10 h-10 rounded-xl flex items-center justify-center transition-all duration-300 ${
                    currentRole === 'sales'
                      ? 'bg-gradient-to-br from-amber-400 to-amber-600 shadow-[0_3px_10px_-2px_rgba(217,119,6,0.4)]'
                      : 'bg-white/[0.06]'
                  }`}>
                    <svg className={`w-5 h-5 transition-colors duration-300 ${currentRole === 'sales' ? 'text-white' : 'text-white/50'}`} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                  </div>
                  <span className={`text-xs font-semibold tracking-wide transition-colors duration-300 ${currentRole === 'sales' ? 'text-white/90' : 'text-white/50'}`}>Sales</span>
                </button>

                { }
                <button
                  type="button"
                  onClick={() => switchRole('customer')}
                  className="flex flex-col items-center gap-2 py-3.5 px-2 rounded-2xl border-[1.5px] border-white/[0.07] bg-white/[0.03] opacity-35 cursor-not-allowed select-none relative"
                >
                  <span className="absolute top-1.5 right-1.5 text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded-md bg-white/[0.08] text-white/35">Soon</span>
                  <div className="w-10 h-10 rounded-xl flex items-center justify-center bg-white/[0.06]">
                    <svg className="w-5 h-5 text-white/50" fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                  </div>
                  <span className="text-xs font-semibold tracking-wide text-white/50">Customer</span>
                </button>
              </div>

              { }
              <div className="flex items-center gap-3 mb-6 stagger stagger-3">
                <div className="flex-1 h-px bg-white/[0.06]" />
                <span className="text-[11px] font-medium text-white/25 uppercase tracking-widest transition-opacity duration-300">
                  {dividerLabels[currentRole]}
                </span>
                <div className="flex-1 h-px bg-white/[0.06]" />
              </div>

              { }
              {error && (
                <div className="flex items-center gap-2.5 px-3.5 py-3 rounded-xl bg-red-500/10 border border-red-500/15 mb-5 error-animate">
                  <svg className="w-[18px] h-[18px] text-red-400 shrink-0" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                  </svg>
                  <span className="text-[13px] text-red-300 leading-snug">{error}</span>
                </div>
              )}

              { }
              <div className="stagger stagger-4" style={{ minHeight: currentRole === 'customer' ? 200 : 295 }}>

                { }
                {currentRole !== 'customer' && (
                  <form onSubmit={handleSubmit}>
                    <div className="mb-4">
                      <label className="block text-[13px] font-semibold text-white/55 mb-2">Username</label>
                      <div className="relative">
                        <input
                          type="text"
                          value={username}
                          onChange={e => setUsername(e.target.value)}
                          placeholder={currentRole === 'sales' ? 'Masukkan username sales' : 'Masukkan username'}
                          required
                          autoComplete="username"
                          className={`login-input ${currentRole === 'sales' ? 'accent-amber' : 'accent-blue'} w-full py-3.5 pl-11 pr-4 rounded-xl text-white text-sm outline-none`}
                        />
                        <div className="absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none text-white/25">
                          <svg className="w-[18px] h-[18px]" fill="none" stroke="currentColor" strokeWidth="1.5" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                          </svg>
                        </div>
                      </div>
                    </div>

                    <div className="mb-4">
                      <label className="block text-[13px] font-semibold text-white/55 mb-2">Password</label>
                      <div className="relative">
                        <input
                          type={showPassword ? 'text' : 'password'}
                          value={password}
                          onChange={e => setPassword(e.target.value)}
                          placeholder="Masukkan password"
                          required
                          autoComplete="current-password"
                          className={`login-input ${currentRole === 'sales' ? 'accent-amber' : 'accent-blue'} w-full py-3.5 pl-11 pr-11 rounded-xl text-white text-sm outline-none`}
                        />
                        <div className="absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none text-white/25">
                          <svg className="w-[18px] h-[18px]" fill="none" stroke="currentColor" strokeWidth="1.5" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                          </svg>
                        </div>
                        <button
                          type="button"
                          onClick={() => setShowPassword(!showPassword)}
                          className="absolute right-3 top-1/2 -translate-y-1/2 p-1 text-white/30 hover:text-white/60 rounded-md transition-colors"
                        >
                          {showPassword ? (
                            <svg className="w-[18px] h-[18px]" fill="none" stroke="currentColor" strokeWidth="1.5" viewBox="0 0 24 24">
                              <path strokeLinecap="round" strokeLinejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>
                            </svg>
                          ) : (
                            <svg className="w-[18px] h-[18px]" fill="none" stroke="currentColor" strokeWidth="1.5" viewBox="0 0 24 24">
                              <path strokeLinecap="round" strokeLinejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                              <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                          )}
                        </button>
                      </div>
                    </div>

                    <div className="flex justify-end mb-5">
                      <button
                        type="button"
                        onClick={() => setShowForgotModal(true)}
                        className={`text-xs font-medium transition-colors ${
                          currentRole === 'sales'
                            ? 'text-amber-600/70 hover:text-amber-300'
                            : 'text-blue-500/70 hover:text-blue-400'
                        }`}
                      >
                        Lupa password?
                      </button>
                    </div>

                    <button
                      type="submit"
                      disabled={submitting}
                      className={`w-full py-3.5 rounded-xl text-sm font-bold text-white flex items-center justify-center gap-2 tracking-wide transition-all relative overflow-hidden ${
                        submitting ? 'opacity-70 pointer-events-none' : 'hover:opacity-90 active:scale-[0.98]'
                      } ${
                        currentRole === 'sales'
                          ? 'bg-gradient-to-br from-amber-400 to-amber-600 shadow-[0_4px_16px_-4px_rgba(217,119,6,0.4)]'
                          : 'bg-gradient-to-br from-blue-500 to-blue-600 shadow-[0_4px_16px_-4px_rgba(37,99,235,0.4)]'
                      }`}
                    >
                      {submitting ? (
                        <div className="w-5 h-5 border-[2.5px] border-white/30 border-t-white rounded-full login-spinner" />
                      ) : (
                        <>
                          <span>Masuk sebagai {currentRole === 'sales' ? 'Sales' : 'Staff'}</span>
                          <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                          </svg>
                        </>
                      )}
                    </button>
                  </form>
                )}

                { }
                {currentRole === 'customer' && (
                  <div className="text-center py-8 px-2">
                    <div className="w-[60px] h-[60px] rounded-[20px] bg-emerald-500/[0.08] border border-emerald-500/[0.12] inline-flex items-center justify-center mb-4">
                      <svg className="w-7 h-7 text-emerald-400" fill="none" stroke="currentColor" strokeWidth="1.5" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                      </svg>
                    </div>
                    <h3 className="text-base font-bold text-white/80 mb-2">Coming Soon</h3>
                    <p className="text-[13px] text-white/35 leading-relaxed max-w-[260px] mx-auto">
                      Portal Customer sedang dalam tahap pengembangan. Hubungi administrator untuk info lebih lanjut.
                    </p>
                  </div>
                )}
              </div>

            </div>
          </div>

          { }
          <div className="mt-6 text-center stagger stagger-6">
            <p className="text-[11px] text-white/20">&copy; 2026 PT. Artha Solusi Aditama. All rights reserved.</p>
          </div>
        </div>

        { }
        {showForgotModal && (
          <div
            className="fixed inset-0 bg-black/55 backdrop-blur-sm z-50 flex items-center justify-center p-4 modal-fade"
            onClick={() => setShowForgotModal(false)}
          >
            <div
              className="login-card w-full max-w-[400px] rounded-3xl p-8 relative modal-slide"
              onClick={e => e.stopPropagation()}
            >
              <button
                onClick={() => setShowForgotModal(false)}
                className="absolute top-4 right-4 w-8 h-8 rounded-[10px] bg-white/5 text-white/40 hover:bg-white/10 hover:text-white/70 flex items-center justify-center transition-all"
              >
                <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>

              <div className="text-center mb-6">
                <div className="w-[52px] h-[52px] rounded-2xl bg-blue-500/[0.12] border border-blue-500/20 inline-flex items-center justify-center mb-3.5">
                  <svg className="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" strokeWidth="1.5" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"/>
                  </svg>
                </div>
                <h3 className="text-lg font-bold text-white">Lupa Password?</h3>
                <p className="text-[13px] text-white/40 mt-1">Hubungi administrator untuk reset password</p>
              </div>

              <div className="text-center py-4 px-2">
                <p className="text-sm text-white/50 leading-relaxed">
                  Silakan hubungi administrator melalui email atau kontak internal untuk mendapatkan bantuan reset password.
                </p>
              </div>

              <button
                onClick={() => setShowForgotModal(false)}
                className="w-full mt-4 py-3 rounded-xl text-sm font-bold text-white bg-gradient-to-br from-blue-500 to-blue-600 shadow-[0_4px_16px_-4px_rgba(37,99,235,0.4)] hover:opacity-90 active:scale-[0.98] transition-all"
              >
                Tutup
              </button>

              <p className="text-[11px] text-white/25 text-center mt-5">
                Jika tidak bisa menghubungi administrator, silakan kunjungi kantor langsung
              </p>
            </div>
          </div>
        )}
      </div>
    </>
  )
}

export default Login
