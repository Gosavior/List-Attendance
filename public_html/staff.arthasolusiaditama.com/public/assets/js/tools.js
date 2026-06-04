document.addEventListener('DOMContentLoaded', function () {
  const API = (typeof TOOLS_API_URL !== 'undefined') ? TOOLS_API_URL : 'app/pages/tools.php';
  const USER_ID = (typeof CURRENT_USER_ID !== 'undefined') ? Number(CURRENT_USER_ID) : 0;
  const ROLE = (typeof CURRENT_ROLE !== 'undefined') ? String(CURRENT_ROLE) : '';

  
  const $ = (s, root = document) => root.querySelector(s);
  const $$ = (s, root = document) => Array.from(root.querySelectorAll(s));

  
  
  function resolveToolPhoto(path) {
    if (!path) return '';
    if (/^https?:\/\//i.test(path)) return path;
    return './' + path.replace(/^\.?\//, '');
  }

  
  const modalIds = ['modalDetail','modalAddCompany','modalAddPersonal','modalTechTools','modalEditPersonal','modalLoan','modalReturn','modalHandover','modalProject','modalBulkReturn','modalEditProject','modalProjectHandover','modalAddApd','modalApdLoan','modalApdReturn','modalAssignApd'];

  function isAnyModalOpen() {
    return modalIds.some(id => {
      const el = document.getElementById(id);
      return el && !el.classList.contains('hidden');
    });
  }

  const show = (el) => {
    if (!el) return;
    el.classList.add('flex');
    el.classList.remove('hidden');
    
    document.documentElement.style.overflow = 'hidden';
  };
  const hide = (el) => {
    if (!el) return;
    el.classList.remove('flex');
    el.classList.add('hidden');
    if (!isAnyModalOpen()) {
      document.documentElement.style.overflow = '';
    }
  };
  const toJSON = async (res) => {
    const txt = await res.text();
    if (!res.ok) {
      try {
        const data = txt ? JSON.parse(txt) : null;
        const msg = data && (data.error || data.message) ? (data.error || data.message) : (txt || res.statusText || `HTTP ${res.status}`);
        throw new Error(msg);
      } catch (e) {
        throw new Error(txt || res.statusText || `HTTP ${res.status}`);
      }
    }
    try { return txt ? JSON.parse(txt) : null; } catch (e) { return txt; }
  };

  const fetchJSON = (url, opts = {}) => fetch(url, opts).then(res => toJSON(res));

  
  
  function getCSRFToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) return meta.getAttribute('content');
    const input = document.querySelector('input[name="csrf_token"]');
    if (input) return input.value;
    return (typeof CSRF_TOKEN !== 'undefined') ? CSRF_TOKEN : '';
  }

  
  function appendCSRF(formData) {
    if (formData instanceof FormData) {
      if (!formData.has('csrf_token')) {
        formData.append('csrf_token', getCSRFToken());
      }
    }
    return formData;
  }

  
  function refreshHeaderNotifications(type) {
    if (typeof fetchAndRenderNotifications === 'function') {
      fetchAndRenderNotifications();
    }
    if (window._globalSocket && window._globalSocket.connected) {
      window._globalSocket.emit('broadcast_notification', { type: type || 'tool_action' });
    }
  }

  
  function showToast(message, type = 'success') {
    let container = document.getElementById('toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      container.className = 'fixed top-4 right-4 space-y-2';
      container.style.cssText = 'max-width: 400px; z-index: 80;';
      document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    const bgColor = type === 'error' ? 'bg-red-600' : type === 'warning' ? 'bg-yellow-500' : 'bg-green-600';
    const icon = type === 'error' ? '<i class="fas fa-times-circle"></i>' : type === 'warning' ? '<i class="fas fa-exclamation-triangle"></i>' : '<i class="fas fa-check-circle"></i>';
    
    toast.className = `${bgColor} text-white px-6 py-4 rounded-lg shadow-lg flex items-center gap-3 animate-slide-in-right transform transition-all duration-300`;
    toast.style.cssText = 'animation: slideInRight 0.3s ease-out;';
    toast.innerHTML = `
      <span class="text-xl">${icon}</span>
      <span class="flex-1">${message}</span>
      <button class="text-white hover:text-gray-200 font-bold ml-2" onclick="this.parentElement.remove()">&times;</button>
    `;

    container.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateX(100%)';
      setTimeout(() => toast.remove(), 300);
    }, 3000);
  }

  function showCenterNotification(message, type = 'success') {
    const existing = document.getElementById('center-notification');
    if (existing) existing.remove();

    const overlay = document.createElement('div');
    overlay.id = 'center-notification';
    overlay.className = 'fixed inset-0 z-[9999] flex items-center justify-center bg-black bg-opacity-50 animate-fade-in';
    
    const bgColor = type === 'error' ? 'bg-red-600' : type === 'warning' ? 'bg-yellow-500' : 'bg-green-600';
    const icon = type === 'error' ? '<i class="fas fa-times-circle"></i>' : type === 'warning' ? '<i class="fas fa-exclamation-triangle"></i>' : '<i class="fas fa-check-circle"></i>';
    
    overlay.innerHTML = `
      <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl p-8 max-w-md w-full mx-4 transform animate-scale-in">
        <div class="flex flex-col items-center text-center">
          <div class="${bgColor} rounded-full p-6 mb-4">
            <span class="text-5xl">${icon}</span>
          </div>
          <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-3">
            ${type === 'success' ? 'Berhasil!' : type === 'error' ? 'Gagal!' : 'Perhatian!'}
          </h3>
          <p class="text-gray-600 dark:text-gray-300 text-lg mb-6">${message}</p>
          <button onclick="document.getElementById('center-notification').remove()" 
                  class="${bgColor} hover:opacity-90 text-white font-semibold py-3 px-8 rounded-lg transition-all duration-200 transform hover:scale-105">
            OK
          </button>
        </div>
      </div>
    `;

    document.body.appendChild(overlay);

    setTimeout(() => {
      if (overlay && overlay.parentNode) {
        overlay.style.opacity = '0';
        setTimeout(() => overlay.remove(), 300);
      }
    }, 2500);

    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) {
        overlay.remove();
      }
    });
  }

  if (!document.getElementById('notification-styles')) {
    const style = document.createElement('style');
    style.id = 'notification-styles';
    style.textContent = `
      @keyframes slideInRight {
        from {
          opacity: 0;
          transform: translateX(100%);
        }
        to {
          opacity: 1;
          transform: translateX(0);
        }
      }
      @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
      }
      @keyframes scaleIn {
        from {
          opacity: 0;
          transform: scale(0.8);
        }
        to {
          opacity: 1;
          transform: scale(1);
        }
      }
      .animate-fade-in {
        animation: fadeIn 0.3s ease-out;
      }
      .animate-scale-in {
        animation: scaleIn 0.3s ease-out;
      }
    `;
    document.head.appendChild(style);
  }

  
  async function compressImage(file, maxW = 1280, quality = 0.7) {
    if (!file || !file.type.startsWith('image/') || file.size < 204800) return file;
    return new Promise(resolve => {
      const img = new Image();
      img.onload = () => {
        let w = img.width, h = img.height;
        if (w > maxW) { h = Math.round(h * maxW / w); w = maxW; }
        if (h > maxW) { w = Math.round(w * maxW / h); h = maxW; }
        const c = document.createElement('canvas');
        c.width = w; c.height = h;
        c.getContext('2d').drawImage(img, 0, 0, w, h);
        c.toBlob(b => {
          URL.revokeObjectURL(img.src);
          if (!b) return resolve(file);
          resolve(new File([b], file.name.replace(/\.[^.]+$/, '.jpg'), { type: 'image/jpeg' }));
        }, 'image/jpeg', quality);
      };
      img.onerror = () => resolve(file);
      img.src = URL.createObjectURL(file);
    });
  }

  async function compressFormImages(fd) {
    for (const [key, val] of [...fd.entries()]) {
      if (val instanceof File && val.size > 204800 && val.type.startsWith('image/')) {
        fd.set(key, await compressImage(val));
      }
    }
    return fd;
  }

  function setSubmitting(btn, on) {
    if (!btn) return;
    if (on) {
      btn.disabled = true;
      btn.dataset.origHtml = btn.innerHTML;
      btn.innerHTML = '<svg class="animate-spin h-5 w-5 inline mr-1" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Mengirim...';
    } else {
      btn.disabled = false;
      btn.innerHTML = btn.dataset.origHtml || 'Kirim';
    }
  }

  $$('[data-close]').forEach(btn => {
    btn.addEventListener('click', (e) => {
      const id = btn.getAttribute('data-close');
      const m = document.getElementById(id);
      if (m) hide(m);
    });
  });

  
  modalIds.forEach(id => {
    const overlay = document.getElementById(id);
    if (!overlay) return;
    overlay.addEventListener('click', (e) => {
      
      if (e.target === overlay) hide(overlay);
    });
    
    overlay.addEventListener('touchmove', (e) => {
      
      const modalContent = overlay.querySelector(':scope > div');
      if (modalContent && !modalContent.contains(e.target)) {
        e.preventDefault();
      }
    }, { passive: false });
  });

  window.refreshAllToolsData = function() {
      toolsCache = [];
      companyCache = [];
      projectCache = [];
      techCache = [];
      apdCache = [];
      
      if (typeof loadTools === 'function') {
          loadTools(true);
      }
      if (typeof loadTechnicians === 'function') {
          loadTechnicians(true);
      }
      if (typeof loadApd === 'function') {
          loadApd(true);
      }
  };

  const tabButtons = $$('[data-tab]');
  function activateTab(name) {
    ['tools','apd','personal'].forEach(t => {
      const sec = document.getElementById('tab-' + t);
      if (sec) sec.classList.add('hidden');
    });
    tabButtons.forEach(b => b.classList.remove('bg-white','dark:bg-gray-900','border','border-b-0'));
    const btn = tabButtons.find(b => b.dataset.tab === name);
    if (btn) btn.classList.add('bg-white','dark:bg-gray-900','border','border-b-0');
    const secShow = document.getElementById('tab-' + name);
    if (secShow) secShow.classList.remove('hidden');

    if (name === 'tools') loadTools(true);
    if (name === 'apd') loadApd(true);
    if (name === 'personal') loadTechnicians(true);
  }

  tabButtons.forEach(b => b.addEventListener('click', () => activateTab(b.dataset.tab)));

  activateTab('tools');

  
  let toolsCache = [];
  
  let companyCache = [];
  let projectCache = [];
  const tblTools = $('#tblTools');

  function renderTools() {
      if (!tblTools) return;
      const q = ($('#searchTools')?.value || '').toLowerCase();
      tblTools.innerHTML = '';
      
      const statusOrder = { 'Loan': 0, 'Handover': 1, 'Project': 2, 'Ready': 3 };
      toolsCache
          .filter(t => (t.name || '').toLowerCase().includes(q) || (t.code || '').toLowerCase().includes(q) || (t.holder_name || '').toLowerCase().includes(q))
          .sort((a, b) => {
              const aIsMine = Number(a.holder_id || 0) === USER_ID ? 0 : 1;
              const bIsMine = Number(b.holder_id || 0) === USER_ID ? 0 : 1;
              if (aIsMine !== bIsMine) return aIsMine - bIsMine;
              const sByStatus = (statusOrder[a.current_status] ?? 9) - (statusOrder[b.current_status] ?? 9);
              if (sByStatus !== 0) return sByStatus;
              return (a.name||'').localeCompare(b.name||'');
          })
          .forEach(t => {
              const isHolder = Number(t.holder_id || 0) === USER_ID;
              const disabled = t.current_status !== 'Ready' ? 'disabled' : '';
              const isChecked = projectCheckedIds.has(String(t.id)) && !disabled ? 'checked' : '';
              const checkedHtml = `<input type="checkbox" class="proj-chk" value="${t.id}" ${disabled} ${isChecked}>`;
              const photoHtml = t.photo_path ? `<img src="${resolveToolPhoto(t.photo_path)}" class="h-10 w-10 object-cover rounded">` : '-';

              
              let statusBadge = '';
              if (t.current_status === 'Loan' || t.current_status === 'Project') {
                statusBadge = `<span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">Dipinjam</span>`;
              } else if (t.current_status === 'Handover') {
                statusBadge = `<span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">Serah Terima</span>`;
              }

              
              let actionHtml = '';
              if (t.current_status === 'Ready') {
                actionHtml = `<button data-act="loan" data-id="${t.id}" class="px-2 py-1 bg-blue-600 text-white text-xs rounded">Pinjam</button>`;
              } else if (t.current_status === 'Loan' || t.current_status === 'Handover' || t.current_status === 'Project') {
                if (isHolder) {
                  actionHtml = `<button data-act="return" data-id="${t.id}" class="px-2 py-1 bg-yellow-500 text-white text-xs rounded">Kembalikan</button>`;
                } else if (ROLE === 'administrator' || ROLE === 'direktur') {
                  actionHtml = `<div class="flex flex-wrap gap-1">
                    <button data-act="force_return" data-id="${t.id}" class="px-2 py-1 bg-red-600 text-white text-xs rounded whitespace-nowrap">Paksa Kembali</button>
                    <button data-act="handover" data-id="${t.id}" class="px-2 py-1 bg-indigo-500 text-white text-xs rounded whitespace-nowrap">Serah Terima</button>
                  </div>`;
                } else {
                  actionHtml = `<button data-act="handover" data-id="${t.id}" class="px-2 py-1 bg-indigo-500 text-white text-xs rounded">Serah Terima</button>`;
                }
              } else {
                actionHtml = '-';
              }

              
              let statusHtml = '';
              if (t.current_status === 'Ready') {
                statusHtml = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Tersedia</span>`;
              } else {
                const holderText = t.holder_name ? escapeHtml(t.holder_name) : '';
                const locText = t.holder_location ? escapeHtml(t.holder_location) : '';
                const displayStatus = t.current_status === 'Project' ? 'Dipinjam' : (t.current_status === 'Loan' ? 'Dipinjam' : 'Serah Terima');
                const statusBgClass = (t.current_status === 'Loan' || t.current_status === 'Project') ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800';
                statusHtml = `<div class="text-xs space-y-0.5">
                  <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${statusBgClass}">${escapeHtml(displayStatus)}</span>
                  ${holderText ? `<div class="mt-1 text-gray-600 dark:text-gray-400"><i class="fas fa-user text-xs mr-1"></i>${holderText}</div>` : ''}
                  ${locText ? `<div class="text-gray-500 dark:text-gray-400"><i class="fas fa-map-marker-alt text-xs mr-1"></i>${locText}</div>` : ''}
                </div>`;
              }

              
              if (t.current_status !== 'Ready' && t.holder_end_date) {
                const endD = new Date(t.holder_end_date);
                const isOverdue = endD < new Date();
                const dateStr = endD.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
                statusHtml += `<div class="mt-0.5 flex items-center gap-1">
                  <span class="text-[10px] ${isOverdue ? 'text-red-600 font-semibold' : 'text-gray-400'}">
                    <i class="fas fa-clock mr-0.5"></i>${dateStr}${isOverdue ? ' (Terlambat)' : ''}
                  </span>
                  ${(ROLE === 'administrator' || ROLE === 'direktur') ? `<button data-act="edit_date" data-id="${t.id}" data-end="${t.holder_end_date}" class="text-[10px] px-1.5 py-0.5 rounded bg-amber-50 text-amber-600 hover:bg-amber-100 dark:bg-amber-900/30 dark:text-amber-400 font-medium transition-colors" title="Ubah Jatuh Tempo"><i class="fas fa-pen" style="font-size:8px"></i></button>` : ''}
                </div>`;
              }

              const tr = document.createElement('tr');
              tr.className = t.current_status !== 'Ready' ? 'bg-gray-50 dark:bg-gray-800/50 cursor-pointer' : 'hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer';
              tr.dataset.id = t.id;
              tr.innerHTML = `
                <td class="px-4 py-2">${checkedHtml}</td>
                <td class="px-4 py-2">${escapeHtml(t.name)}${statusBadge}</td>
                <td class="px-4 py-2">${escapeHtml(t.code)}</td>
                <td class="px-4 py-2">${photoHtml}</td>
                <td class="px-4 py-2">${actionHtml}</td>
                <td class="px-4 py-2">${statusHtml}</td>
              `;

              tr.addEventListener('click', (e) => {
                  if (e.target.closest('button') || e.target.closest('input[type="checkbox"]')) return;
                  openToolDetail(t.id);
              });

              
              tr.querySelectorAll('button[data-act]').forEach(btn => {
                  btn.addEventListener('click', (ev) => {
                      ev.stopPropagation();
                      const act = btn.dataset.act;
                      const id = btn.dataset.id;
                      if (act === 'loan') {
                          document.getElementById("loanToolId").value = id;
                          const nowDT = new Date();
                          const nowStr = nowDT.getFullYear() + '-' + String(nowDT.getMonth()+1).padStart(2,'0') + '-' + String(nowDT.getDate()).padStart(2,'0') + 'T' + String(nowDT.getHours()).padStart(2,'0') + ':' + String(nowDT.getMinutes()).padStart(2,'0');
                          const loanForm = document.getElementById('formLoan');
                          const loanStart = loanForm.querySelector('input[name="start_date"]');
                          const loanEnd = loanForm.querySelector('input[name="end_date"]');
                          loanStart.value = nowStr;
                          loanStart.min = nowStr;
                          loanEnd.min = nowStr;
                          loanStart.addEventListener('change', function() { loanEnd.min = this.value; });
                          const selWrapper = document.getElementById("loanToUser").closest('div');
                          if (ROLE === 'administrator' || ROLE === 'direktur' || ROLE === 'internship') {
                              selWrapper.style.display = '';
                              fetchJSON(API + '?action=list_technicians').then(techs => {
                                  const sel = document.getElementById("loanToUser");
                                  sel.innerHTML = '<option value="">-- Pilih PIC / Teknisi --</option>';
                                  (techs || []).forEach(t => {
                                      const opt = document.createElement('option');
                                      opt.value = t.id; opt.textContent = t.full_name + (t.role === 'sales' ? ' (Sales)' : '');
                                      sel.appendChild(opt);
                                  });
                              });
                          } else {
                              selWrapper.style.display = 'none';
                              document.getElementById("loanToUser").innerHTML = `<option value="${USER_ID}" selected>${USER_ID}</option>`;
                          }
                          show(document.getElementById("modalLoan"));
                      }
                      else if (act === 'handover') { openHandoverModal(id); }
                      else if (act === 'return') { openReturnModal(id); }
                      else if (act === 'force_return') {
                          if (confirm('Apakah Anda yakin ingin memaksa pengembalian? Alat akan langsung dikembalikan ke PT. Artha Solusi Aditama.')) {
                              doForceReturn(id);
                          }
                      }
                      else if (act === 'edit_date') { openEditDateModal(id, btn.dataset.end); }
                  });
              });

              
              tr.querySelectorAll('button[data-pact]').forEach(btn => {
                  btn.addEventListener('click', (ev) => {
                      ev.stopPropagation();
                      const pact = btn.dataset.pact;
                      const id = btn.dataset.id;
                      if (pact === 'edit') { openEditProjectModal(id, t); }
                      else if (pact === 'return_project') { openReturnProjectOneModal(id, t); }
                      else if (pact === 'proj_handover_req') { openProjectHandoverModal(id, t); }
                  });
              });

              tblTools.appendChild(tr);
          });

      
      const projectAll = toolsCache.filter(t => t.current_status === 'Project');
      const projectCountForUser = projectAll.filter(t => t.holder_id && Number(t.holder_id) === Number(CURRENT_USER_ID)).length;
      const btnBulk = document.getElementById('btnBulkReturnProject');
      if (btnBulk) {
        const visible = (ROLE === 'administrator' || ROLE === 'direktur') ? projectAll.length > 0 : projectCountForUser > 0;
        if (visible) {
          btnBulk.classList.remove('hidden');
          const labelCount = (ROLE === 'administrator' || ROLE === 'direktur') ? projectAll.length : projectCountForUser;
          btnBulk.textContent = 'Kembalikan Semua Alat (' + labelCount + ')';
        } else {
          btnBulk.classList.add('hidden');
        }
      }
      const btnForceAll = document.getElementById('btnForceReturnAllProject');
      if (btnForceAll) {
        if (projectAll.length > 0) {
          btnForceAll.classList.remove('hidden');
          btnForceAll.textContent = 'Kembalikan Paksa Semua (' + projectAll.length + ')';
        } else {
          btnForceAll.classList.add('hidden');
        }
      }
      updateProjectButtonState();
  }

  function loadTools(force = false) {
    if (!force && toolsCache.length) return renderTools();
    fetchJSON(API + '?action=list_company_tools')
      .then(data => {
        toolsCache = Array.isArray(data) ? data : [];
        companyCache = toolsCache;
        projectCache = toolsCache;
        renderTools();
      })
      .catch(err => {
        console.error('loadTools error', err);
        if (tblTools) tblTools.innerHTML = `<tr><td colspan="6" class="px-4 py-4 text-red-600">Gagal memuat data.</td></tr>`;
      });
  }

  
  function loadCompany(force) { loadTools(force); }
  function loadProject(force) { loadTools(force); }
  function renderCompany() { renderTools(); }
  function renderProject() { renderTools(); }

  function doReturn(toolId) {
    document.getElementById("returnToolId").value = toolId;
    show(document.getElementById("modalReturn"));
  }

  async function doForceReturn(toolId) {
    const fd = new FormData();
    fd.append('action', 'force_return');
    fd.append('tool_id', toolId);
    try {
      const j = await fetchJSON(API, { method: 'POST', body: fd });
      if (j.error) return showToast(j.error, 'error');
      showCenterNotification(j.message || 'Return paksa berhasil', 'success');
      loadTools(true);
      refreshHeaderNotifications('force_return');
    } catch (err) { showToast(String(err), 'error'); }
  }

  $('#btnAddCompanyTool')?.addEventListener('click', () => show($('#modalAddCompany')));
  $('#searchTools')?.addEventListener('input', renderTools);
  $('#formAddCompany')?.addEventListener('submit', async function (ev) {
    ev.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    setSubmitting(btn, true);
    try {
      const fd = await compressFormImages(new FormData(this));
      fd.append('action', 'add_company_tool');
      const j = await fetchJSON(API, { method: 'POST', body: fd });
      if (j.error) { showToast(j.error, 'error'); return; }
      showToast(j.message || 'Berhasil ditambahkan', 'success');
      hide($('#modalAddCompany'));
      this.reset();
      loadTools(true);
    } catch (err) { showToast(String(err), 'error'); }
    finally { setSubmitting(btn, false); }
  });

  let projectCheckedIds = new Set();

  function updateProjectButtonState() {
    const any = projectCheckedIds.size > 0;
    const btn = $('#btnSubmitProject');
    if (btn) btn.disabled = !any;
  }

  document.addEventListener('change', (e) => {
    if (e.target.classList.contains('proj-chk')) {
      const val = e.target.value;
      if (e.target.checked) {
        projectCheckedIds.add(val);
      } else {
        projectCheckedIds.delete(val);
      }
      updateProjectButtonState();
    }
  });

  $('#checkAllProject')?.addEventListener('change', function () {
    const on = this.checked;
    $$('.proj-chk').forEach(c => {
      if (!c.disabled) {
        c.checked = on;
        if (on) {
          projectCheckedIds.add(c.value);
        } else {
          projectCheckedIds.delete(c.value);
        }
      }
    });
    updateProjectButtonState();
  });

  $('#btnSubmitProject')?.addEventListener('click', function () {
    const ids = Array.from(projectCheckedIds);
    if (!ids.length) return showToast('Pilih minimal 1 alat', 'warning');
    
    document.getElementById("projectToolIds").value = ids.join(',');
    const now = new Date();
    const pad = n => String(n).padStart(2, '0');
    const localDT = now.getFullYear() + '-' + pad(now.getMonth()+1) + '-' + pad(now.getDate()) + 'T' + pad(now.getHours()) + ':' + pad(now.getMinutes());
    const startEl = document.getElementById('projectStartDate');
    if (startEl) startEl.value = localDT;
    const projEndEl = document.getElementById('formProject')?.querySelector('input[name="end_date"]');
    if (projEndEl) projEndEl.min = localDT;
    show(document.getElementById("modalProject"));
  });

  const projectCameraButtons = document.querySelectorAll('#modalProject .camera-btn');
  const projectPhotoInputDesktop = document.getElementById('proof_photo_desktop');
  
  const projectMobileFiles = new DataTransfer();

  if (projectCameraButtons) {
      projectCameraButtons.forEach(btn => {
          btn.addEventListener('click', function() {
              const source = this.dataset.source;
              
              let mobileInput = document.getElementById('proof_photo_mobile');
              if (!mobileInput) {
                  mobileInput = document.createElement('input');
                  mobileInput.type = 'file';
                  mobileInput.id = 'proof_photo_mobile';
                  mobileInput.name = 'proof_photos_mobile[]';
                  mobileInput.accept = 'image/*';
                  mobileInput.style.display = 'none';
                  document.getElementById('formProject').appendChild(mobileInput);
                  
                  mobileInput.addEventListener('change', function(e) {
                      if (this.files.length > 0) {
                          for (const f of this.files) {
                              projectMobileFiles.items.add(f);
                          }
                          
                          if (projectPhotoInputDesktop) {
                              projectPhotoInputDesktop.files = projectMobileFiles.files;
                          }
                          previewProjectPhotos(projectMobileFiles.files);
                      }
                  });
              }
              
              if (source === 'camera') {
                  mobileInput.setAttribute('capture', 'environment');
              } else {
                  mobileInput.removeAttribute('capture');
              }
              
              mobileInput.click();
          });
      });
  }

  function previewProjectPhotos(files) {
      const projectPhotoPreview = document.getElementById('projectPhotoPreview');
      if (!projectPhotoPreview) return;
      projectPhotoPreview.innerHTML = '';
      if (!files || files.length === 0) {
          projectPhotoPreview.classList.add('hidden');
          return;
      }
      for (const file of files) {
          const wrapper = document.createElement('div');
          wrapper.className = 'relative';
          const img = document.createElement('img');
          img.src = URL.createObjectURL(file);
          img.className = 'w-24 h-24 object-cover rounded border border-gray-300 dark:border-gray-600';
          wrapper.appendChild(img);
          projectPhotoPreview.appendChild(wrapper);
      }
      projectPhotoPreview.classList.remove('hidden');
  }

  if (projectPhotoInputDesktop) {
      projectPhotoInputDesktop.addEventListener('change', function(e) {
          previewProjectPhotos(this.files);
      });
  }

  const formProject = document.getElementById('formProject');
  if (formProject) {
      formProject.addEventListener('submit', async function(ev) {
          ev.preventDefault();
          const btn = this.querySelector('button[type="submit"]');
          const toolIds = this.querySelector('input[name="tool_ids"]').value.split(',');
          
          
          const startInput = this.querySelector('input[name="start_date"]').value;
          const endInput = this.querySelector('input[name="end_date"]').value;
          if (new Date(endInput) <= new Date(startInput)) {
              return showCenterNotification('Jatuh tempo harus setelah tanggal mulai', 'warning');
          }

          setSubmitting(btn, true);
          try {
            const fd = new FormData(this);
            
            const photoFiles = fd.getAll('proof_photos[]');
            fd.delete('proof_photos[]');
            for (const file of photoFiles) {
                if (file instanceof File && file.size > 0) {
                    const compressed = await compressImage(file);
                    fd.append('proof_photos[]', compressed);
                }
            }
            fd.append('action', 'project_request');
            toolIds.forEach(id => { if (id) fd.append('tool_ids[]', id); });
            const j = await fetchJSON(API, { method: 'POST', body: fd });
            if (j.error) { showToast(j.error, 'error'); return; }
            showCenterNotification(j.message || 'Pengajuan project dikirim', 'success');
            hide(document.getElementById('modalProject'));
            this.reset();
            projectCheckedIds.clear();
            
            while (projectMobileFiles.items.length > 0) projectMobileFiles.items.remove(0);
            refreshHeaderNotifications('project_request');
            const projectPhotoPreview = document.getElementById('projectPhotoPreview');
            if (projectPhotoPreview) { projectPhotoPreview.innerHTML = ''; projectPhotoPreview.classList.add('hidden'); }
            loadProject(true);
            loadCompany(true);
          } catch (err) { showToast(String(err), 'error'); }
          finally { setSubmitting(btn, false); }
      });
  }
  $('#searchProject')?.addEventListener('input', renderProject);

  
  function openEditProjectModal(toolId, t) {
    document.getElementById('editProjectToolId').value = toolId;
    document.getElementById('editProjectPicName').value = t.project_name || '';
    document.getElementById('editProjectLocation').value = t.holder_location || '';
    
    const toLocalDT = (v) => {
      if (!v) return '';
      if (v.length === 10) return v + 'T00:00'; 
      return v.replace(' ', 'T').slice(0, 16);   
    };
    document.getElementById('editProjectStartDate').value = toLocalDT(t.project_start_date);
    document.getElementById('editProjectEndDate').value = toLocalDT(t.project_end_date);

    
    const sel = document.getElementById('editProjectTechnician');
    if (sel) {
      sel.innerHTML = '<option value="">Memuat...</option>';
      fetchJSON(API + '?action=list_technicians').then(techs => {
        sel.innerHTML = '<option value="">-- Pilih Teknisi --</option>';
        (techs || []).forEach(tech => {
          const opt = document.createElement('option');
          opt.value = tech.id;
          opt.textContent = tech.full_name + (tech.role === 'sales' ? ' (Sales)' : '');
          if (t.holder_id && String(tech.id) === String(t.holder_id)) opt.selected = true;
          sel.appendChild(opt);
        });
      }).catch(() => { sel.innerHTML = '<option value="">Gagal memuat teknisi</option>'; });
    }

    show(document.getElementById('modalEditProject'));
  }

  const formEditProject = document.getElementById('formEditProject');
  if (formEditProject) {
    formEditProject.addEventListener('submit', async function(ev) {
      ev.preventDefault();
      const btn = this.querySelector('button[type="submit"]');
      setSubmitting(btn, true);
      try {
        const fd = new FormData(this);
        fd.append('action', 'edit_project');
        const j = await fetchJSON(API, { method: 'POST', body: fd });
        if (j.error) { showToast(j.error, 'error'); return; }
        showCenterNotification(j.message || 'Data project berhasil diperbarui', 'success');
        hide(document.getElementById('modalEditProject'));
        this.reset();
        loadProject(true);
        refreshHeaderNotifications('edit_project');
      } catch (err) { showToast(String(err), 'error'); }
      finally { setSubmitting(btn, false); }
    });
  }

  
  
  function openProjectHandoverModal(toolId, t) {
    document.getElementById('projectHandoverToolId').value = toolId;

    
    const hiddenToUser = document.getElementById('projectHandoverToUser');
    const isSelect = hiddenToUser && hiddenToUser.tagName === 'SELECT';

    if (isSelect) {
      
      fetchJSON(API + '?action=list_technicians').then(techs => {
        hiddenToUser.innerHTML = '<option value="">-- Pilih Teknisi --</option>';
        (techs || []).forEach(tech => {
          
          if (t.holder_id && String(tech.id) === String(t.holder_id)) return;
          const opt = document.createElement('option');
          opt.value = tech.id;
          opt.textContent = tech.full_name + (tech.role === 'sales' ? ' (Sales)' : '');
          hiddenToUser.appendChild(opt);
        });
      }).catch(err => console.error('Failed to load technicians:', err));
    } else if (hiddenToUser) {
      hiddenToUser.value = USER_ID;
    }

    
    const infoText = document.getElementById('projectHandoverToolInfoText');
    if (infoText) {
      infoText.innerHTML = `
        <div><span class="font-semibold">Tool:</span> ${escapeHtml(t.name)} <span class="text-gray-400 text-xs">(${escapeHtml(t.code)})</span></div>
        <div class="mt-1 text-blue-700 dark:text-blue-300">Anda akan mengajukan request handover kepada: <strong>${escapeHtml(t.holder_name || '-')}</strong></div>
      `;
    }

    
    const fldProjectName = document.getElementById('projHandoverProjectName');
    const fldLocation    = document.getElementById('projHandoverLocation');
    const fldPicName     = document.getElementById('projHandoverPicName');
    if (fldProjectName) fldProjectName.value = t.project_name    || '';
    if (fldLocation)    fldLocation.value    = t.holder_location  || '';
    if (fldPicName)     fldPicName.value     = t.project_name     || '';

    show(document.getElementById('modalProjectHandover'));
  }

  const formProjectHandover = document.getElementById('formProjectHandover');
  if (formProjectHandover) {
    
    const projHandoverCamBtns = document.querySelectorAll('#modalProjectHandover .proj-handover-cam-btn');
    const projHandoverPhotoDesktop = document.getElementById('proj_handover_photo_desktop');
    const projHandoverPhotoPreview = document.getElementById('projHandoverPhotoPreview');

    projHandoverCamBtns.forEach(btn => {
      btn.addEventListener('click', function() {
        const source = this.dataset.source;
        let mobileInput = document.getElementById('proj_handover_photo_mobile');
        if (!mobileInput) {
          mobileInput = document.createElement('input');
          mobileInput.type = 'file';
          mobileInput.id = 'proj_handover_photo_mobile';
          mobileInput.name = 'proof_photo';
          mobileInput.accept = 'image/*';
          mobileInput.style.display = 'none';
          formProjectHandover.appendChild(mobileInput);
          mobileInput.addEventListener('change', function() {
            if (projHandoverPhotoDesktop && this.files.length > 0) {
              const dt = new DataTransfer();
              dt.items.add(this.files[0]);
              projHandoverPhotoDesktop.files = dt.files;
            }
            if (this.files[0] && projHandoverPhotoPreview) {
              projHandoverPhotoPreview.querySelector('img').src = URL.createObjectURL(this.files[0]);
              projHandoverPhotoPreview.classList.remove('hidden');
            }
          });
        }
        if (source === 'camera') { mobileInput.setAttribute('capture', 'environment'); }
        else { mobileInput.removeAttribute('capture'); }
        mobileInput.click();
      });
    });

    if (projHandoverPhotoDesktop) {
      projHandoverPhotoDesktop.addEventListener('change', function() {
        if (this.files[0] && projHandoverPhotoPreview) {
          projHandoverPhotoPreview.querySelector('img').src = URL.createObjectURL(this.files[0]);
          projHandoverPhotoPreview.classList.remove('hidden');
        }
      });
    }

    formProjectHandover.addEventListener('submit', async function(ev) {
      ev.preventDefault();
      const btn = this.querySelector('button[type="submit"]');
      setSubmitting(btn, true);
      try {
        const fd = await compressFormImages(new FormData(this));
        fd.append('action', 'project_handover');
        const j = await fetchJSON(API, { method: 'POST', body: fd });
        if (j.error) { showToast(j.error, 'error'); return; }
        showCenterNotification(j.message || 'Handover project berhasil', 'success');
        hide(document.getElementById('modalProjectHandover'));
        this.reset();
        if (projHandoverPhotoPreview) projHandoverPhotoPreview.classList.add('hidden');
        loadProject(true);
        refreshHeaderNotifications('project_handover');
      } catch (err) { showToast(String(err), 'error'); }
      finally { setSubmitting(btn, false); }
    });
  }

  
  async function doForceReturnOne(toolId) {
    const fd = new FormData();
    fd.append('action', 'force_return');
    fd.append('tool_id', toolId);
    try {
      const j = await fetchJSON(API, { method: 'POST', body: fd });
      if (j.error) return showToast(j.error, 'error');
      showCenterNotification(j.message || 'Return paksa berhasil', 'success');
      loadProject(true);
      loadCompany(true);
      refreshHeaderNotifications('force_return');
    } catch (err) { showToast(String(err), 'error'); }
  }

  
  function openReturnProjectOneModal(toolId, t) {
    
    const selectAllChk = document.getElementById('bulkReturnSelectAll');
    if (selectAllChk) selectAllChk.closest('label')?.parentElement?.classList.add('hidden');
    
    const searchInput = document.getElementById('bulkReturnSearch');
    if (searchInput) searchInput.parentElement.classList.add('hidden');
    const countEl = document.getElementById('bulkReturnSelectedCount');
    if (countEl) countEl.textContent = '';
    
    const listEl = document.getElementById('bulkReturnToolList');
    if (listEl) {
      listEl.innerHTML = `<div class="flex items-center gap-3 px-3 py-3">
        <div class="w-8 h-8 rounded-lg bg-yellow-100 dark:bg-yellow-900/40 flex items-center justify-center text-yellow-600 dark:text-yellow-300 text-sm shrink-0"><i class="fas fa-wrench"></i></div>
        <div class="flex-1 min-w-0">
          <div class="font-medium text-gray-800 dark:text-gray-200">${escapeHtml(t.name)}</div>
          <div class="text-xs text-gray-400">${escapeHtml(t.code)}</div>
        </div>
      </div>`;
    }
    
    const submitBtn = document.getElementById('btnBulkReturnSubmit');
    if (submitBtn) { submitBtn.textContent = 'Kembalikan Alat'; submitBtn.disabled = false; submitBtn.style.opacity = '1'; }
    
    const form = document.getElementById('formBulkReturn');
    let hiddenSingle = form.querySelector('input[name="single_tool_id"]');
    if (!hiddenSingle) {
      hiddenSingle = document.createElement('input');
      hiddenSingle.type = 'hidden';
      hiddenSingle.name = 'single_tool_id';
      form.appendChild(hiddenSingle);
    }
    hiddenSingle.value = toolId;
    show(document.getElementById('modalBulkReturn'));
  }


  document.getElementById('btnForceReturnAllProject')?.addEventListener('click', function() {
    const projectAll = projectCache.filter(t => t.current_status === 'Project');
    if (!projectAll.length) return showToast('Tidak ada alat dalam status Project', 'warning');
    if (!confirm(`Apakah Anda yakin ingin memaksa pengembalian SEMUA ${projectAll.length} alat yang sedang dalam project? Semua alat akan langsung dikembalikan ke Tersedia.`)) return;
    const fd = new FormData();
    fd.append('action', 'force_return_all_project');
    fetchJSON(API, { method: 'POST', body: fd })
      .then(j => {
        if (j.error) return showToast(j.error, 'error');
        showCenterNotification(j.message || 'Paksa kembali semua project berhasil', 'success');
        loadProject(true);
        loadCompany(true);
        refreshHeaderNotifications('force_return_all');
      })
      .catch(err => showToast(String(err), 'error'));
  });

  
    
    window._bulkReturnChecked = new Set();

    $('#btnBulkReturnProject')?.addEventListener('click', function() {
      
      const selectAllRow = document.getElementById('bulkReturnSelectAll')?.closest('.flex')?.parentElement?.querySelector('.flex');
      const selectAllLabel = document.getElementById('bulkReturnSelectAll')?.closest('label');
      if (selectAllLabel) selectAllLabel.parentElement.classList.remove('hidden');
      
      const searchInput = document.getElementById('bulkReturnSearch');
      if (searchInput) { searchInput.value = ''; searchInput.parentElement.classList.remove('hidden'); }
      
      const projectTools = projectCache.filter(t => t.current_status === 'Project' && ( (typeof CURRENT_ROLE !== 'undefined' && (CURRENT_ROLE === 'administrator' || CURRENT_ROLE === 'direktur')) || (t.holder_id && Number(t.holder_id) === Number(CURRENT_USER_ID)) ));
      
      window._bulkReturnTools = projectTools;
      
      window._bulkReturnChecked = new Set(projectTools.map(t => String(t.id)));
      renderBulkReturnList(projectTools, '');
      
      const selectAllChk = document.getElementById('bulkReturnSelectAll');
      if (selectAllChk) selectAllChk.checked = true;
      updateBulkReturnCount();
      
      const form = document.getElementById('formBulkReturn');
      const hiddenSingle = form ? form.querySelector('input[name="single_tool_id"]') : null;
      if (hiddenSingle) hiddenSingle.value = '';
      show(document.getElementById('modalBulkReturn'));
  });

  
  document.getElementById('bulkReturnSearch')?.addEventListener('input', function() {
    const q = this.value.toLowerCase().trim();
    const tools = window._bulkReturnTools || [];
    renderBulkReturnList(tools, q);
    
    const all = document.querySelectorAll('.bulk-return-chk');
    const checked = document.querySelectorAll('.bulk-return-chk:checked');
    const selectAllChk = document.getElementById('bulkReturnSelectAll');
    if (selectAllChk) selectAllChk.checked = (all.length > 0 && all.length === checked.length);
    updateBulkReturnCount();
  });

  function renderBulkReturnList(tools, query) {
    const listEl = document.getElementById('bulkReturnToolList');
    if (!listEl) return;
    const checkedSet = window._bulkReturnChecked || new Set();
    const filtered = query ? tools.filter(t => (t.name||'').toLowerCase().includes(query) || (t.code||'').toLowerCase().includes(query) || (t.holder_name||'').toLowerCase().includes(query) || (t.holder_location||'').toLowerCase().includes(query)) : tools;
    if (filtered.length === 0) {
      listEl.innerHTML = '<div class="px-3 py-4 text-center text-gray-400 text-sm">Tidak ada tools ditemukan</div>';
      return;
    }
    listEl.innerHTML = filtered.map(t => {
      const isChecked = checkedSet.has(String(t.id)) ? 'checked' : '';
      const loc = t.holder_location ? escapeHtml(t.holder_location) : '';
      return `<label class="flex items-center gap-3 px-3 py-2.5 cursor-pointer hover:bg-orange-50 dark:hover:bg-gray-800/80 transition-colors">
        <input type="checkbox" class="bulk-return-chk rounded border-gray-300 text-orange-500 focus:ring-orange-400" value="${t.id}" ${isChecked}>
        <div class="flex-1 min-w-0">
          <div class="font-medium text-gray-800 dark:text-gray-200 truncate">${escapeHtml(t.name)}</div>
          <div class="text-xs text-gray-400 flex items-center gap-2 mt-0.5 flex-wrap">
            <span>${escapeHtml(t.code)}</span>
            ${t.holder_name ? `<span class="text-gray-300 dark:text-gray-600">&bull;</span><span><i class="fas fa-user text-[10px]"></i> ${escapeHtml(t.holder_name)}</span>` : ''}
            ${loc ? `<span class="text-gray-300 dark:text-gray-600">&bull;</span><span class="text-blue-500 dark:text-blue-400"><i class="fas fa-map-marker-alt text-[10px]"></i> ${loc}</span>` : ''}
          </div>
        </div>
        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-yellow-100 dark:bg-yellow-900/40 text-yellow-700 dark:text-yellow-300 shrink-0">Loan</span>
      </label>`;
    }).join('');
  }

  
  document.getElementById('bulkReturnSelectAll')?.addEventListener('change', function() {
      const checkedSet = window._bulkReturnChecked || new Set();
      document.querySelectorAll('.bulk-return-chk').forEach(chk => {
          chk.checked = this.checked;
          if (this.checked) checkedSet.add(chk.value);
          else checkedSet.delete(chk.value);
      });
      updateBulkReturnCount();
  });

  
  document.addEventListener('change', function(e) {
      if (e.target.classList.contains('bulk-return-chk')) {
          const checkedSet = window._bulkReturnChecked || new Set();
          if (e.target.checked) checkedSet.add(e.target.value);
          else checkedSet.delete(e.target.value);
          const all = document.querySelectorAll('.bulk-return-chk');
          const checked = document.querySelectorAll('.bulk-return-chk:checked');
          const selectAllChk = document.getElementById('bulkReturnSelectAll');
          if (selectAllChk) selectAllChk.checked = (all.length === checked.length && all.length > 0);
          updateBulkReturnCount();
      }
  });

  function updateBulkReturnCount() {
      const checkedSet = window._bulkReturnChecked || new Set();
      const total = document.querySelectorAll('.bulk-return-chk');
      const countEl = document.getElementById('bulkReturnSelectedCount');
      if (countEl) countEl.textContent = `${checkedSet.size} / ${(window._bulkReturnTools || []).length} dipilih`;
      const submitBtn = document.getElementById('btnBulkReturnSubmit');
      if (submitBtn) {
          submitBtn.innerHTML = `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/></svg> Return Selected (${checkedSet.size})`;
          submitBtn.disabled = checkedSet.size === 0;
          submitBtn.style.opacity = checkedSet.size === 0 ? '0.5' : '1';
      }
  }

  const bulkCameraButtons = document.querySelectorAll('#modalBulkReturn .camera-btn-bulk');
  const bulkReturnPhotoDesktop = document.getElementById('bulk_return_photo_desktop');

  if (bulkCameraButtons) {
      bulkCameraButtons.forEach(btn => {
          btn.addEventListener('click', function() {
              const source = this.dataset.source;
              let mobileInput = document.getElementById('bulk_return_photo_mobile');
              if (!mobileInput) {
                  mobileInput = document.createElement('input');
                  mobileInput.type = 'file';
                  mobileInput.id = 'bulk_return_photo_mobile';
                  mobileInput.name = 'return_photo';
                  mobileInput.accept = 'image/*';
                  mobileInput.style.display = 'none';
                  document.getElementById('formBulkReturn').appendChild(mobileInput);
                  mobileInput.addEventListener('change', function(e) {
                      if (bulkReturnPhotoDesktop && this.files.length > 0) {
                          const dataTransfer = new DataTransfer();
                          dataTransfer.items.add(this.files[0]);
                          bulkReturnPhotoDesktop.files = dataTransfer.files;
                      }
                      previewBulkPhoto(this.files[0]);
                  });
              }
              if (source === 'camera') {
                  mobileInput.setAttribute('capture', 'environment');
              } else {
                  mobileInput.removeAttribute('capture');
              }
              mobileInput.click();
          });
      });
  }

  function previewBulkPhoto(file) {
      const preview = document.getElementById('bulkReturnPhotoPreview');
      if (file && preview) {
          const img = preview.querySelector('img');
          img.src = URL.createObjectURL(file);
          preview.classList.remove('hidden');
      }
  }

  if (bulkReturnPhotoDesktop) {
      bulkReturnPhotoDesktop.addEventListener('change', function(e) {
          previewBulkPhoto(this.files[0]);
      });
  }

  const formBulkReturn = document.getElementById('formBulkReturn');
  if (formBulkReturn) {
      formBulkReturn.addEventListener('submit', async function(ev) {
          ev.preventDefault();
          const btn = this.querySelector('button[type="submit"]');
          setSubmitting(btn, true);
          try {
              const fd = await compressFormImages(new FormData(this));
              fd.append('action', 'bulk_return_project');
              
              const singleId = fd.get('single_tool_id');
              if (!singleId) {
                  const checkedSet = window._bulkReturnChecked || new Set();
                  if (checkedSet.size === 0) {
                      showToast('Pilih minimal 1 tool untuk di-return', 'warning');
                      return;
                  }
                  checkedSet.forEach(id => fd.append('tool_ids[]', id));
              }
              const j = await fetchJSON(API, { method: 'POST', body: fd });
              if (j.error) { showToast(j.error, 'error'); return; }
              showCenterNotification(j.message || 'Bulk return berhasil', 'success');
              hide(document.getElementById('modalBulkReturn'));
              this.reset();
              refreshHeaderNotifications('bulk_return');
              
              const hiddenSingle = this.querySelector('input[name="single_tool_id"]');
              if (hiddenSingle) hiddenSingle.value = '';
              const preview = document.getElementById('bulkReturnPhotoPreview');
              if (preview) preview.classList.add('hidden');
              loadProject(true);
              loadCompany(true);
          } catch (err) { showToast(String(err), 'error'); }
          finally { setSubmitting(btn, false); }
      });
  }

  
  let techCache = [];
  let _currentTechUser = null;
  function renderTechnicians() {
    const q = ($('#searchTechnician')?.value || '').toLowerCase();
    const grid = $('#gridTechnicians');
    if (!grid) return;
    grid.innerHTML = '';
    techCache.filter(t => (t.full_name||'').toLowerCase().includes(q)).forEach(t => {
      const card = document.createElement('div');
      card.className = 'p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 flex items-center gap-3 hover:shadow cursor-pointer';
      const avatar = t.avatar || './public/assets/images/avatar-default.png';
      const roleMap = {
        'sales': 'Sales',
        'hse': 'HSE',
        'daily': 'Daily',
        'technician_manager': 'Technician Manager',
        'technician': 'Teknisi'
      };
      const roleLabel = roleMap[t.role] || (t.role ? (t.role.charAt(0).toUpperCase() + t.role.slice(1)) : 'Teknisi');
      card.innerHTML = `<img src="${avatar}" class="h-12 w-12 rounded-full object-cover bg-gray-200"><div><div class="font-medium">${escapeHtml(t.full_name)}</div><div class="text-xs text-gray-500">${roleLabel}</div></div>`;
      card.addEventListener('click', () => openTechTools(t));
      grid.appendChild(card);
    });

    
    const sel = $('#assignToSelect');
    if (sel) {
      sel.innerHTML = `<option value="">-- pilih teknisi --</option>`;
      techCache.forEach(t => {
        const opt = document.createElement('option');
        opt.value = t.id; 
        opt.textContent = t.full_name;
        sel.appendChild(opt);
      });
    }
  }

  function loadTechnicians(force = false) {
    if (!force && techCache.length) return renderTechnicians();
    fetchJSON(API + '?action=list_technicians').then(data => {
      techCache = Array.isArray(data) ? data : [];
      renderTechnicians();
    }).catch(err => {
      $('#gridTechnicians').innerHTML = `<div class="text-red-600">Gagal memuat teknisi.</div>`;
    });
  }

  $('#searchTechnician')?.addEventListener('input', renderTechnicians);

  function openTechTools(user) {
    $('#techToolsTitle').textContent = (user.full_name || '') + ' - Alat';
    _currentTechUser = user;
    window._currentTechUser = user;
    const isAdmin = ['administrator','direktur','hse'].includes(CURRENT_ROLE);
    Promise.all([
      fetchJSON(API + '?action=list_personal_tools&technician_id=' + encodeURIComponent(user.id)),
      fetchJSON(API + '?action=list_apd')
    ]).then(([list, apdList]) => {
      const tb = $('#tblTechTools');
      tb.innerHTML = '';
      (list || []).forEach(t => {
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-gray-50 dark:hover:bg-gray-800';
        const indicatorColor = t.current_status === 'Good' ? 'bg-green-500' : (t.current_status === 'Repair' ? 'bg-yellow-500' : (t.current_status === 'Missing' ? 'bg-red-600 animate-pulse' : 'bg-gray-400'));
        let actionHtml = '';
        if (isAdmin) {
          actionHtml = `<td class="px-4 py-2 whitespace-nowrap">
            <button onclick="editPersonalTool(${t.id}, this)" data-tool='${escapeHtml(JSON.stringify(t))}' class="text-blue-600 hover:text-blue-800 text-xs font-medium mr-2"><i class="fas fa-pen mr-0.5"></i>Edit</button>
            <button onclick="deletePersonalTool(${t.id})" class="text-red-600 hover:text-red-800 text-xs font-medium"><i class="fas fa-trash mr-0.5"></i>Hapus</button>
          </td>`;
        }
        tr.innerHTML = `<td class="px-4 py-2">${escapeHtml(t.name)}</td>
                        <td class="px-4 py-2">${escapeHtml(t.code)}</td>
                        <td class="px-4 py-2">${escapeHtml(t.condition_notes || '-')}</td>
                        <td class="px-4 py-2"><span class="inline-block w-3 h-3 rounded-full ${indicatorColor} mr-2"></span>${escapeHtml(t.current_status || '')}</td>${actionHtml}`;
        tb.appendChild(tr);
      });
      
      const thead = $('#modalTechTools thead tr');
      if (thead) {
        const existingAction = thead.querySelector('.col-action');
        if (isAdmin && !existingAction) {
          const th = document.createElement('th');
          th.className = 'px-4 py-2 col-action';
          th.textContent = 'Aksi';
          thead.appendChild(th);
        } else if (!isAdmin && existingAction) {
          existingAction.remove();
        }
      }

      
      const tblApd = $('#tblTechApd');
      const apdEmpty = $('#techApdEmpty');
      const apdSection = $('#techApdSection');
      if (tblApd) {
        tblApd.innerHTML = '';
        const userApd = (apdList || []).filter(a => Number(a.holder_id || 0) === Number(user.id));
        if (userApd.length > 0) {
          if (apdEmpty) apdEmpty.classList.add('hidden');
          userApd.forEach(a => {
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-gray-50 dark:hover:bg-gray-800';
            const dotColor = a.current_status === 'Ready' ? 'bg-green-500'
                            : a.current_status === 'Assigned' ? 'bg-teal-500'
                            : a.current_status === 'Loan' ? 'bg-yellow-500'
                            : a.current_status === 'Handover' ? 'bg-blue-500'
                            : 'bg-gray-400';
            tr.innerHTML = `
              <td class="px-4 py-2">${escapeHtml(a.name || '')}</td>
              <td class="px-4 py-2">${escapeHtml(a.code || '')}</td>
              <td class="px-4 py-2">
                <span class="inline-flex items-center">
                  <span class="w-3 h-3 rounded-full ${dotColor} mr-2"></span>
                  <span class="text-sm">${escapeHtml(a.current_status || '')}</span>
                </span>
              </td>`;
            tblApd.appendChild(tr);
          });
        } else {
          if (apdEmpty) apdEmpty.classList.remove('hidden');
        }
      }

      show($('#modalTechTools'));
    }).catch(err => showToast(String(err), 'error'));
  }

  
  window.editPersonalTool = function(toolId, btn) {
    const t = JSON.parse(btn.dataset.tool);
    const $id = document.getElementById('editPersToolId');
    const $name = document.getElementById('editPersName');
    const $code = document.getElementById('editPersCode');
    const $status = document.getElementById('editPersStatus');
    const $notes = document.getElementById('editPersNotes');
    if ($id) $id.value = toolId;
    if ($name) $name.value = t.name || '';
    if ($code) $code.value = t.code || '';
    if ($status) $status.value = t.current_status || 'Good';
    if ($notes) $notes.value = t.condition_notes || '';
    show(document.getElementById('modalEditPersonal'));
  };

  
  window.deletePersonalTool = async function(toolId) {
    if (!confirm('Yakin ingin menghapus personal tool ini?')) return;
    try {
      const fd = new FormData();
      fd.append('action', 'delete_personal_tool');
      fd.append('tool_id', toolId);
      const j = await fetchJSON(API, { method: 'POST', body: fd });
      if (j.error) { showToast(j.error, 'error'); return; }
      showToast(j.message || 'Personal tool dihapus', 'success');
      hide(document.getElementById('modalTechTools'));
      loadTechnicians(true);
    } catch (err) { showToast(String(err), 'error'); }
  };

  
  const formEditPers = document.getElementById('formEditPersonal');
  if (formEditPers) {
    formEditPers.addEventListener('submit', async function(ev) {
      ev.preventDefault();
      const btn = this.querySelector('button[type="submit"]');
      setSubmitting(btn, true);
      try {
        const fd = await compressFormImages(new FormData(this));
        fd.append('action', 'edit_personal_tool');
        const j = await fetchJSON(API, { method: 'POST', body: fd });
        if (j.error) { showToast(j.error, 'error'); return; }
        showToast(j.message || 'Personal tool diperbarui', 'success');
        hide(document.getElementById('modalEditPersonal'));
        hide(document.getElementById('modalTechTools'));
        loadTechnicians(true);
      } catch (err) { showToast(String(err), 'error'); }
      finally { setSubmitting(btn, false); }
    });
  }

  $('#btnAddPersonalTool')?.addEventListener('click', () => {
    loadTechnicians(true);
    show($('#modalAddPersonal'));
  });

  $('#formAddPersonal')?.addEventListener('submit', async function (ev) {
    ev.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    setSubmitting(btn, true);
    try {
      const fd = await compressFormImages(new FormData(this));
      fd.append('action', 'add_personal_tool');
      const j = await fetchJSON(API, { method: 'POST', body: fd });
      if (j.error) { showToast(j.error, 'error'); return; }
      showToast(j.message || 'Personal tool ditambahkan', 'success');
      hide($('#modalAddPersonal'));
      this.reset();
      loadTechnicians(true);
    } catch (err) { showToast(String(err), 'error'); }
    finally { setSubmitting(btn, false); }
  });

  loadCompany(true);
  loadProject(true);
  loadTechnicians(true);

  function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }
  function $trim(s) { return (s||'').toString().trim(); }

  $('#formLoan')?.addEventListener('submit', async function (ev) {
      ev.preventDefault();
      
      const loanStartVal = this.querySelector('input[name="start_date"]').value;
      const loanEndVal = this.querySelector('input[name="end_date"]').value;
      if (loanStartVal && loanEndVal && new Date(loanEndVal) <= new Date(loanStartVal)) {
          return showCenterNotification('Jatuh tempo harus setelah tanggal mulai pinjam', 'warning');
      }
      const btn = this.querySelector('button[type="submit"]');
      setSubmitting(btn, true);
      try {
        const fd = await compressFormImages(new FormData(this));
        fd.append('action', 'loan_request');
        const j = await fetchJSON(API, { method: 'POST', body: fd });
        if (j.error) { showCenterNotification(j.error, 'error'); return; }
        showCenterNotification(j.message || 'Permintaan berhasil dikirim', 'success');
        hide($('#modalLoan'));
        this.reset();
        loadCompany(true);
        refreshHeaderNotifications('loan_request');
      } catch (err) { showCenterNotification(String(err), 'error'); }
      finally { setSubmitting(btn, false); }
  });

    
    function openReturnModal(toolId) {
        document.getElementById("returnToolId").value = toolId;
        show(document.getElementById("modalReturn"));
    }

    const cameraButtons = document.querySelectorAll('#modalReturn .camera-btn');
    const returnPhotoInputDesktop = document.getElementById('return_photo_desktop');

    if (cameraButtons) {
        cameraButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const source = this.dataset.source;
                
                let mobileInput = document.getElementById('return_photo_mobile');
                if (!mobileInput) {
                    mobileInput = document.createElement('input');
                    mobileInput.type = 'file';
                    mobileInput.id = 'return_photo_mobile';
                    mobileInput.name = 'return_photo';
                    mobileInput.accept = 'image/*';
                    mobileInput.style.display = 'none';
                    document.getElementById('formReturn').appendChild(mobileInput);
                    
                    mobileInput.addEventListener('change', function(e) {
                        if (returnPhotoInputDesktop && this.files.length > 0) {
                            const dataTransfer = new DataTransfer();
                            dataTransfer.items.add(this.files[0]);
                            returnPhotoInputDesktop.files = dataTransfer.files;
                        }
                        previewPhoto(this.files[0]);
                    });
                }
                
                if (source === 'camera') {
                    mobileInput.setAttribute('capture', 'environment');
                } else {
                    mobileInput.removeAttribute('capture');
                }
                
                mobileInput.click();
            });
        });
    }

    function previewPhoto(file) {
        const returnPhotoPreview = document.getElementById('returnPhotoPreview');
        if (file && returnPhotoPreview) {
            const img = returnPhotoPreview.querySelector('img');
            img.src = URL.createObjectURL(file);
            returnPhotoPreview.classList.remove('hidden');
        }
    }

    if (returnPhotoInputDesktop) {
        returnPhotoInputDesktop.addEventListener('change', function(e) {
            previewPhoto(this.files[0]);
        });
    }

    const formReturn = document.getElementById('formReturn');
    if (formReturn) {
        formReturn.addEventListener('submit', async function(ev) {
            ev.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            setSubmitting(btn, true);
            try {
              const fd = await compressFormImages(new FormData(this));
              fd.append('action', 'return_request');
              const j = await fetchJSON(API, { method: 'POST', body: fd });
              if (j.error) { showToast(j.error, 'error'); return; }
              showCenterNotification(j.message || 'Pengajuan pengembalian dikirim', 'success');
              hide(document.getElementById('modalReturn'));
              this.reset();
              refreshHeaderNotifications('return_request');
              const returnPhotoPreview = document.getElementById('returnPhotoPreview');
              if (returnPhotoPreview) returnPhotoPreview.classList.add('hidden');
              loadCompany(true);
            } catch (err) { showToast(String(err), 'error'); }
            finally { setSubmitting(btn, false); }
        });
    }

    document.addEventListener('click', function(e) {
        if (e.target && e.target.hasAttribute('data-act') && e.target.getAttribute('data-act') === 'return') {
            const toolId = e.target.getAttribute('data-id');
            openReturnModal(toolId);
        }
    });

  function openHandoverModal(toolId) {
    document.getElementById("handoverToolId").value = toolId;
    
    fetchJSON(API + '?action=tool_detail&tool_id=' + encodeURIComponent(toolId))
        .then(data => {
            const tool = data.tool || {};
            const holder = data.holder || {};
            
            const modal = document.getElementById("modalHandover");
            
            const existingInfo = modal.querySelector('.handover-info');
            if (existingInfo) {
                existingInfo.remove();
            }
            
            const infoElement = document.createElement('div');
            infoElement.className = 'handover-info bg-blue-50 dark:bg-blue-900/30 p-3 rounded mb-3 text-sm text-gray-800 dark:text-gray-200';
            let infoHtml = `<div class="font-medium mb-1">Info Handover:</div>`;
            infoHtml += `<div>Dari: <strong>${escapeHtml(holder.full_name || 'Pemegang tidak diketahui')}</strong></div>`;
            if (holder.location) {
                infoHtml += `<div>Lokasi saat ini: <strong>${escapeHtml(holder.location)}</strong></div>`;
            }
            infoElement.innerHTML = infoHtml;
            
            const form = modal.querySelector('form');
            if (form && form.parentNode) {
                form.parentNode.insertBefore(infoElement, form);
            } else {
                modal.appendChild(infoElement);
            }
            
            return fetchJSON(API + '?action=list_technicians');
        })
        .then(techs => {
            const select = document.getElementById("handoverToUser");
            select.innerHTML = '<option value="">-- Pilih Staff --</option>';
            (techs || []).forEach(t => {
                const opt = document.createElement('option');
                opt.value = t.id; opt.textContent = t.full_name + (t.role === 'sales' ? ' (Sales)' : '');
                select.appendChild(opt);
            });
            
            show(document.getElementById("modalHandover"));
        })
        .catch(err => {
            console.error('Handover error:', err);
            showToast('Gagal memuat informasi handover: ' + err.message, 'error');
        });
}

  $('#formHandover')?.addEventListener('submit', async function (ev) {
      ev.preventDefault();
      const btn = this.querySelector('button[type="submit"]');
      setSubmitting(btn, true);
      try {
        const fd = await compressFormImages(new FormData(this));
        fd.append('action', 'handover_request');
        const j = await fetchJSON(API, { method: 'POST', body: fd });
        if (j.error) { showToast(j.error, 'error'); return; }
        showCenterNotification(j.message || 'Permintaan handover dikirim', 'success');
        hide($('#modalHandover'));
        this.reset();
        loadCompany(true);
        refreshHeaderNotifications('handover_request');
      } catch (err) { showToast(String(err), 'error'); }
      finally { setSubmitting(btn, false); }
  });

function toggleLoanExtend(toolId) {
    const el = document.getElementById('loanExtendForm');
    const editEl = document.getElementById('loanEditForm');
    if (editEl) editEl.classList.add('hidden');
    if (el) el.classList.toggle('hidden');
}

function toggleLoanEdit(toolId) {
    const el = document.getElementById('loanEditForm');
    const extendEl = document.getElementById('loanExtendForm');
    if (extendEl) extendEl.classList.add('hidden');
    if (el) el.classList.toggle('hidden');
}

function openEditDateModal(toolId, currentEndDate) {
    let modal = document.getElementById('modalEditDate');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'modalEditDate';
        modal.className = 'hidden fixed inset-0 z-[70] bg-black/50 items-center justify-center p-4';
        modal.innerHTML = `
            <div class="bg-white dark:bg-gray-900 rounded-xl shadow-lg w-full max-w-sm p-5 mx-auto mt-[30vh]">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-9 h-9 rounded-lg bg-amber-100 dark:bg-amber-900/40 flex items-center justify-center">
                        <i class="fas fa-calendar-alt text-amber-600 dark:text-amber-400"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white">Edit Tanggal Pengembalian</h3>
                        <p class="text-[10px] text-gray-400">Ubah jatuh tempo peminjaman</p>
                    </div>
                </div>
                <form id="formEditDate">
                    <input type="hidden" name="tool_id" id="editDateToolId">
                    <div class="mb-4">
                        <label class="text-xs text-gray-500 dark:text-gray-400 mb-1 block">Jatuh Tempo Baru</label>
                        <input name="new_end_date" id="editDateInput" type="datetime-local" class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm p-2.5 rounded-lg focus:ring-2 focus:ring-amber-400 focus:border-amber-400" required>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" onclick="hide(document.getElementById('modalEditDate'))" class="flex-1 px-4 py-2.5 border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 text-sm rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition">Batal</button>
                        <button type="submit" class="flex-1 px-4 py-2.5 bg-amber-600 text-white text-sm font-medium rounded-lg hover:bg-amber-700 transition" style="background-color:#d97706;color:#fff;">Simpan</button>
                    </div>
                </form>
            </div>
        `;
        document.body.appendChild(modal);
        modal.addEventListener('click', function(e) { if (e.target === modal) hide(modal); });
        document.getElementById('formEditDate').addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            setSubmitting(btn, true);
            const fd = new FormData();
            fd.append('action', 'quick_edit_date');
            fd.append('tool_id', document.getElementById('editDateToolId').value);
            fd.append('new_end_date', document.getElementById('editDateInput').value);
            fetch(API, { method: 'POST', body: fd })
                .then(r => toJSON(r))
                .then(j => {
                    if (j.error) return showToast(j.error, 'error');
                    showCenterNotification(j.message || 'Jatuh tempo diperbarui', 'success');
                    hide(modal);
                    loadCompany(true);
                })
                .catch(err => showToast(String(err), 'error'))
                .finally(() => setSubmitting(btn, false));
        });
    }
    document.getElementById('editDateToolId').value = toolId;
    const dtInput = document.getElementById('editDateInput');
    if (currentEndDate) {
        dtInput.value = currentEndDate.replace(' ', 'T').substring(0, 16);
    } else {
        dtInput.value = '';
    }
    show(modal);
}


window.toggleLoanExtend = toggleLoanExtend;
window.toggleLoanEdit = toggleLoanEdit;
window.openEditDateModal = openEditDateModal;

function openToolDetail(id) {
    fetchJSON(API + '?action=tool_detail&tool_id=' + encodeURIComponent(id))
      .then(d => {
        const body = $('#detailBody');
        const t = d.tool || {};
        const holder = d.holder;
        const history = d.history || [];
        const statusColor = t.current_status === 'Ready' ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'
            : (t.current_status === 'Loan' || t.current_status === 'Project') ? 'bg-amber-50 text-amber-700 ring-amber-600/20'
            : t.current_status === 'Handover' ? 'bg-orange-50 text-orange-700 ring-orange-600/20'
            : t.current_status === 'Good' ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'
            : t.current_status === 'Repair' ? 'bg-yellow-50 text-yellow-700 ring-yellow-600/20'
            : t.current_status === 'Missing' ? 'bg-red-50 text-red-700 ring-red-600/20'
            : 'bg-blue-50 text-blue-700 ring-blue-600/20';
        const displayStatus = t.current_status === 'Project' ? 'Dipinjam' : t.current_status;

        
        const permits = d.permits || [];
        let historyHtml = '';
        
        if (permits.length > 0) {
            const permitTypeLabel = (type) => {
                return { loan: 'Pinjam', handover: 'Serah Terima', project: 'Pinjam (Project)', return: 'Dikembalikan', force_return: 'Paksa Kembali', apd_request: 'APD', apd_return: 'APD Kembali' }[type] || type;
            };
            const permitIcon = (type) => {
                return { loan: 'fa-hand-holding', handover: 'fa-exchange-alt', project: 'fa-project-diagram', return: 'fa-undo-alt', force_return: 'fa-exclamation-triangle', apd_request: 'fa-hard-hat', apd_return: 'fa-undo' }[type] || 'fa-circle';
            };
            const permitColor = (type) => {
                return { loan: 'bg-amber-500', handover: 'bg-orange-500', project: 'bg-purple-500', return: 'bg-emerald-500', force_return: 'bg-red-500' }[type] || 'bg-gray-500';
            };
            const statusBadge = (status) => {
                if (status === 'approved') return '<span class="text-[9px] px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 font-bold"><i class="fas fa-check"></i> Approved</span>';
                if (status === 'pending') return '<span class="text-[9px] px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 font-bold animate-pulse"><i class="fas fa-clock"></i> Pending</span>';
                return '<span class="text-[9px] px-1.5 py-0.5 rounded bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 font-bold"><i class="fas fa-times"></i> Rejected</span>';
            };
            const fmtDate = (d) => d ? new Date(d).toLocaleDateString('id-ID', {day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'}) : '-';
            const resolvePhoto = (p) => {
                if (!p) return '';
                if (p.startsWith('http') || p.startsWith('./')) return p;
                return './' + p;
            };

            historyHtml = `
            <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-800">
              <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                  <i class="fas fa-route text-blue-500 text-xs"></i>
                  <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Riwayat (${permits.length})</div>
                </div>
                ${permits.length > 3 ? `<button type="button" onclick="this.closest('.ht-chain').classList.toggle('ht-chain-expanded');this.textContent=this.textContent==='Lihat Semua'?'Sembunyikan':'Lihat Semua'" class="text-[10px] text-blue-600 dark:text-blue-400 font-medium hover:underline">Lihat Semua</button>` : ''}
              </div>
              <div class="space-y-1.5 relative ht-chain">
                ${permits.map((p, idx) => {
                    const isReturn = ['return','force_return'].includes(p.permit_type);
                    const photos = [];
                    if (p.admin_photo_path) photos.push({label:'Admin', src: resolvePhoto(p.admin_photo_path)});
                    const proofPaths = p.photo_proof_path ? (p.photo_proof_path.startsWith('[') ? JSON.parse(p.photo_proof_path) : [p.photo_proof_path]) : [];
                    proofPaths.forEach(pp => { if(pp) photos.push({label:'Bukti', src: resolvePhoto(pp)}); });
                    const hiddenClass = idx >= 3 ? 'ht-chain-item hidden' : '';
                    
                    return `<div class="rounded-lg p-2 text-xs bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700 ${hiddenClass}">
                        <div class="flex items-center gap-2 flex-wrap">
                          <span class="inline-flex items-center gap-1 text-[10px] font-bold px-1.5 py-0.5 rounded ${isReturn ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : p.permit_type === 'project' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'}">
                            <i class="fas ${permitIcon(p.permit_type)}"></i> ${permitTypeLabel(p.permit_type)}
                          </span>
                          ${statusBadge(p.status)}
                          <span class="text-[9px] text-gray-400 ml-auto">${fmtDate(p.created_at)}</span>
                        </div>
                        <div class="flex items-center gap-1.5 text-[11px] mt-1">
                          ${isReturn 
                            ? `<span class="text-gray-500"><i class="fas fa-user mr-0.5"></i>${escapeHtml(p.from_name||'-')}</span> <span class="text-gray-400">→</span> <span class="text-emerald-600 font-semibold dark:text-emerald-400">PT. ASA</span>`
                            : `<span class="text-gray-500"><i class="fas fa-user mr-0.5"></i>${escapeHtml(p.from_name||'-')}</span> <span class="text-gray-400">→</span> <span class="text-amber-600 font-semibold dark:text-amber-400">${escapeHtml(p.to_name||'-')}</span>`
                          }
                        </div>
                        ${p.reason ? `<div class="text-[10px] text-gray-500 mt-0.5"><i class="fas fa-info-circle mr-0.5"></i>${escapeHtml(p.reason)}</div>` : ''}
                        ${p.location ? `<div class="text-[10px] text-gray-400 mt-0.5"><i class="fas fa-map-marker-alt mr-0.5"></i>${escapeHtml(p.location)}</div>` : ''}
                        ${(p.start_date || p.end_date) ? `<div class="text-[10px] text-gray-400 mt-0.5"><i class="fas fa-calendar mr-0.5"></i>${fmtDate(p.start_date)} — ${fmtDate(p.end_date)}</div>` : ''}
                        ${p.approved_at ? `<div class="text-[10px] text-green-500 mt-0.5"><i class="fas fa-check-circle mr-0.5"></i>Approved: ${fmtDate(p.approved_at)}${p.approved_by_name ? ' oleh ' + escapeHtml(p.approved_by_name) : ''}</div>` : ''}
                        ${photos.length > 0 ? `<div class="flex gap-1 mt-1">${photos.map(ph => `<a href="${ph.src}" target="_blank"><img src="${ph.src}" class="w-8 h-8 object-cover rounded border border-gray-200 dark:border-gray-600" onerror="this.style.display='none'"></a>`).join('')}</div>` : ''}
                    </div>`;
                }).join('')}
              </div>
            </div>`;
            
            if (!document.getElementById('htChainStyle')) {
              const style = document.createElement('style');
              style.id = 'htChainStyle';
              style.textContent = '.ht-chain-expanded .ht-chain-item { display: block !important; }';
              document.head.appendChild(style);
            }
        } else if (history.length > 0) {
            
            historyHtml = `
            <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-800">
              <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Riwayat Status</div>
              <div class="max-h-40 overflow-y-auto space-y-1.5">
                ${history.map(h => {
                  const fromColor = h.from_status === 'Ready' || h.from_status === 'Good' ? 'text-green-600' : h.from_status === 'Repair' ? 'text-yellow-600' : h.from_status === 'Missing' ? 'text-red-600' : 'text-blue-600';
                  const toColor = h.to_status === 'Ready' || h.to_status === 'Good' ? 'text-green-600' : h.to_status === 'Repair' ? 'text-yellow-600' : h.to_status === 'Missing' ? 'text-red-600' : 'text-blue-600';
                  const dateStr = h.created_at ? new Date(h.created_at).toLocaleDateString('id-ID', {day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'}) : '-';
                  return `<div class="flex items-start gap-2 text-xs bg-gray-50 dark:bg-gray-800 rounded-md p-2">
                    <div class="flex-1">
                      <span class="${fromColor} font-medium">${escapeHtml(h.from_status||'-')}</span>
                      <span class="text-gray-400 mx-1">→</span>
                      <span class="${toColor} font-medium">${escapeHtml(h.to_status||'-')}</span>
                      ${h.notes ? `<div class="text-gray-500 mt-0.5">${escapeHtml(h.notes)}</div>` : ''}
                    </div>
                    <div class="text-gray-400 whitespace-nowrap text-[10px]">${dateStr}<br>${escapeHtml(h.full_name||'')}</div>
                  </div>`;
                }).join('')}
              </div>
            </div>`;
        }

        
        let editHtml = '';
        if (ROLE === 'administrator' || ROLE === 'direktur') {
            const statusOpts = ['Ready','Loan','Handover','Project','Good','Repair','Missing'];
            editHtml = `
            <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-800">
              <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Edit Tool (Admin)</div>
              <form id="formEditTool" class="space-y-2">
                <input type="hidden" name="tool_id" value="${t.id}">
                <div>
                  <label class="text-xs text-gray-500">Nama</label>
                  <input name="name" type="text" value="${escapeHtml(t.name||'')}" class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm p-2 rounded" required>
                </div>
                <div>
                  <label class="text-xs text-gray-500">Kode</label>
                  <input name="code" type="text" value="${escapeHtml(t.code||'')}" class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm p-2 rounded" required>
                </div>
                <div>
                  <label class="text-xs text-gray-500">Status</label>
                  <select name="current_status" class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm p-2 rounded">
                    ${statusOpts.map(s => `<option value="${s}" ${s === t.current_status ? 'selected' : ''}>${s}</option>`).join('')}
                  </select>
                </div>
                <div>
                  <label class="text-xs text-gray-500">Keterangan / Kondisi</label>
                  <textarea name="condition_notes" rows="2" class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm p-2 rounded">${escapeHtml(t.condition_notes||'')}</textarea>
                </div>
                <div>
                  <label class="text-xs text-gray-500">Ganti Foto (opsional)</label>
                  <input name="photo" type="file" accept="image/*" class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm p-2 rounded">
                </div>
                <div class="flex gap-2 pt-1">
                  <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors" style="background-color:#2563eb;color:#fff;">Simpan Perubahan</button>
                  <button type="button" data-act="delete" data-id="${t.id}" class="px-4 py-2 bg-red-50 hover:bg-red-100 text-red-600 text-sm font-medium rounded-lg transition-colors border border-red-200 hover:border-red-300">Hapus</button>
                </div>
              </form>
            </div>`;
        }

        body.innerHTML = `
          <div class="flex gap-4 items-start">
            <div class="flex-shrink-0 w-24">
              ${t.photo_path
                ? `<div class="rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-1.5"><img src="${resolveToolPhoto(t.photo_path)}" class="w-full h-auto object-contain rounded" alt="${escapeHtml(t.name || 'Tool')}"></div>`
                : `<div class="rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-1.5 flex items-center justify-center h-24"><svg class="w-8 h-8 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085"/></svg></div>`
              }
            </div>
            <div class="flex-1 min-w-0">
              <div class="font-semibold text-gray-900 dark:text-white text-sm leading-snug">${escapeHtml(t.name || '-')}</div>
              <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 font-mono">${escapeHtml(t.code || '-')}</div>
              <div class="text-xs text-gray-400 mt-1">Tipe: ${escapeHtml(t.tool_type || '-')}</div>
              <div class="mt-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ring-1 ring-inset ${statusColor}">${escapeHtml(displayStatus || '-')}</span>
              </div>
              ${t.condition_notes ? `<div class="mt-1.5 text-xs text-gray-600 dark:text-gray-300"><span class="text-gray-400">Kondisi:</span> ${escapeHtml(t.condition_notes)}</div>` : ''}
              ${holder && (t.current_status === 'Loan' || t.current_status === 'Project' || t.current_status === 'Handover') ? `
              <div class="mt-2.5 bg-gray-50 dark:bg-gray-800 rounded-md p-2">
                <div class="flex items-center justify-between mb-1">
                  <div class="text-[10px] text-gray-400 uppercase tracking-wider font-medium">Dipinjam oleh</div>
                  <div class="flex gap-1">
                    ${ROLE === 'administrator' || ROLE === 'direktur' ? `<button type="button" onclick="toggleLoanExtend(${t.id})" class="text-[10px] px-2 py-0.5 rounded bg-blue-50 text-blue-600 hover:bg-blue-100 dark:bg-blue-900/30 dark:text-blue-400 font-medium transition-colors">Perpanjang</button>
                    <button type="button" onclick="toggleLoanEdit(${t.id})" class="text-[10px] px-2 py-0.5 rounded bg-amber-50 text-amber-600 hover:bg-amber-100 dark:bg-amber-900/30 dark:text-amber-400 font-medium transition-colors">Edit</button>` : ''}
                  </div>
                </div>
                <div class="text-sm font-medium text-gray-800 dark:text-gray-200">${escapeHtml(holder.full_name || '-')}</div>
                ${holder.location ? `<div class="text-xs text-gray-500 mt-0.5"><i class="fas fa-map-marker-alt"></i> ${escapeHtml(holder.location)}</div>` : ''}
                ${holder.reason ? `<div class="text-xs text-gray-500 mt-0.5"><i class="fas fa-info-circle"></i> ${escapeHtml(holder.reason)}</div>` : ''}
                ${holder.start_date ? `<div class="text-xs text-gray-400 mt-0.5"><i class="fas fa-calendar"></i> ${new Date(holder.start_date).toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'})} — ${holder.end_date ? new Date(holder.end_date).toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'}) : '-'}</div>` : ''}
                ${ROLE === 'administrator' || ROLE === 'direktur' ? `<div id="loanExtendForm" class="hidden mt-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                  <div class="text-xs font-semibold text-blue-600 dark:text-blue-400 mb-1.5">Perpanjang Peminjaman</div>
                  <form id="formExtendLoan" class="space-y-1.5">
                    <input type="hidden" name="tool_id" value="${t.id}">
                    <div>
                      <label class="text-[10px] text-gray-500">Jatuh Tempo Baru</label>
                      <input name="new_end_date" type="datetime-local" class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-xs p-1.5 rounded" required>
                    </div>
                    <button type="submit" class="w-full px-3 py-1.5 bg-blue-600 text-white text-xs font-medium rounded hover:bg-blue-700 transition-colors" style="background-color:#2563eb;color:#fff;">Perpanjang</button>
                  </form>
                </div>` : ''}
                ${ROLE === 'administrator' || ROLE === 'direktur' ? `
                <div id="loanEditForm" class="hidden mt-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                  <div class="text-xs font-semibold text-amber-600 dark:text-amber-400 mb-1.5">Edit Peminjaman</div>
                  <form id="formEditLoan" class="space-y-1.5">
                    <input type="hidden" name="tool_id" value="${t.id}">
                    <div>
                      <label class="text-[10px] text-gray-500">PIC (Peminjam)</label>
                      <select name="to_user_id" class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-xs p-1.5 rounded" required>
                        <option value="">-- Pilih --</option>
                      </select>
                    </div>
                    <div>
                      <label class="text-[10px] text-gray-500">Keperluan</label>
                      <input name="purpose" type="text" value="${escapeHtml(holder.reason||'')}" class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-xs p-1.5 rounded" required>
                    </div>
                    <div>
                      <label class="text-[10px] text-gray-500">Lokasi</label>
                      <input name="location" type="text" value="${escapeHtml(holder.location||'')}" class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-xs p-1.5 rounded" required>
                    </div>
                    <div class="grid grid-cols-2 gap-1.5">
                      <div>
                        <label class="text-[10px] text-gray-500">Mulai Pinjam</label>
                        <input name="start_date" type="datetime-local" value="${holder.start_date ? holder.start_date.replace(' ','T').substring(0,16) : ''}" class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-xs p-1.5 rounded" required>
                      </div>
                      <div>
                        <label class="text-[10px] text-gray-500">Jatuh Tempo</label>
                        <input name="end_date" type="datetime-local" value="${holder.end_date ? holder.end_date.replace(' ','T').substring(0,16) : ''}" class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-xs p-1.5 rounded" required>
                      </div>
                    </div>
                    <button type="submit" class="w-full px-3 py-1.5 bg-amber-600 text-white text-xs font-medium rounded hover:bg-amber-700 transition-colors" style="background-color:#d97706;color:#fff;">Simpan Perubahan</button>
                  </form>
                </div>` : ''}
              </div>` : holder ? `
              <div class="mt-2.5 bg-gray-50 dark:bg-gray-800 rounded-md p-2">
                <div class="text-[10px] text-gray-400 uppercase tracking-wider font-medium">Dipinjam oleh</div>
                <div class="text-sm font-medium text-gray-800 dark:text-gray-200">${escapeHtml(holder.full_name || '-')}</div>
                ${holder.location ? `<div class="text-xs text-gray-500 mt-0.5"><i class="fas fa-map-marker-alt"></i> ${escapeHtml(holder.location)}</div>` : ''}
              </div>` : ''}
            </div>
          </div>
          ${historyHtml}
          ${editHtml}
        `;
        
        
        const editForm = body.querySelector('#formEditTool');
        if (editForm) {
            editForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                const btn = this.querySelector('button[type="submit"]');
                setSubmitting(btn, true);
                const fd = await compressFormImages(new FormData(this));
                fd.append('action', 'update_tool');
                fetch(API, { method: 'POST', body: fd })
                    .then(r => toJSON(r))
                    .then(j => {
                        if (j.error) return showToast(j.error, 'error');
                        showCenterNotification(j.message || 'Tool diperbarui', 'success');
                        hide($('#modalDetail'));
                        refreshAllToolsData();
                        refreshHeaderNotifications('update_tool');
                    })
                    .catch(err => showToast(String(err), 'error'))
                    .finally(() => setSubmitting(btn, false));
            });
        }

        
        const deleteBtn = body.querySelector('button[data-act="delete"]');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                const toolId = this.dataset.id;
                if (confirm('Apakah Anda yakin ingin menghapus tool ini? Tindakan ini tidak dapat dibatalkan.')) {
                    deleteTool(toolId, t.tool_type);
                }
            });
        }

        
        const extendForm = body.querySelector('#formExtendLoan');
        if (extendForm) {
            extendForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const btn = this.querySelector('button[type="submit"]');
                setSubmitting(btn, true);
                const fd = new FormData(this);
                fd.append('action', 'extend_loan');
                fetch(API, { method: 'POST', body: fd })
                    .then(r => toJSON(r))
                    .then(j => {
                        if (j.error) return showToast(j.error, 'error');
                        showCenterNotification(j.message || 'Peminjaman diperpanjang', 'success');
                        hide($('#modalDetail'));
                        refreshAllToolsData();
                    })
                    .catch(err => showToast(String(err), 'error'))
                    .finally(() => setSubmitting(btn, false));
            });
        }

        
        const editLoanForm = body.querySelector('#formEditLoan');
        if (editLoanForm) {
            
            const sel = editLoanForm.querySelector('select[name="to_user_id"]');
            if (sel) {
                fetchJSON(API + '?action=list_technicians').then(techs => {
                    sel.innerHTML = '<option value="">-- Pilih PIC --</option>';
                    (techs || []).forEach(u => {
                        const opt = document.createElement('option');
                        opt.value = u.id;
                        opt.textContent = u.full_name + (u.role === 'sales' ? ' (Sales)' : '');
                        if (holder && String(u.id) === String(holder.to_user_id)) opt.selected = true;
                        sel.appendChild(opt);
                    });
                });
            }
            editLoanForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const btn = this.querySelector('button[type="submit"]');
                setSubmitting(btn, true);
                const fd = new FormData(this);
                fd.append('action', 'edit_loan');
                fetch(API, { method: 'POST', body: fd })
                    .then(r => toJSON(r))
                    .then(j => {
                        if (j.error) return showToast(j.error, 'error');
                        showCenterNotification(j.message || 'Data peminjaman diperbarui', 'success');
                        hide($('#modalDetail'));
                        refreshAllToolsData();
                    })
                    .catch(err => showToast(String(err), 'error'))
                    .finally(() => setSubmitting(btn, false));
            });
        }
        
        show($('#modalDetail'));
      })
      .catch(err => showToast(String(err), 'error'));
}

function deleteTool(toolId, toolType) {
    const fd = new FormData();
    const action = toolType === 'apd' ? 'delete_apd' : 'delete_company_tool';
    fd.append('action', action);
    fd.append('tool_id', toolId);
    
    fetch(API, { method: 'POST', body: fd })
        .then(res => toJSON(res))
        .then(j => {
            if (j.error) return showToast(j.error, 'error');
            showToast(j.message || 'Tool berhasil dihapus', 'success');
            hide($('#modalDetail'));
            refreshAllToolsData();
        })
        .catch(err => showToast(String(err), 'error'));
}



let apdCache = [];
const tblApd = $('#tblApd');

function renderApd() {
    if (!tblApd) return;
    const q = ($('#searchApd')?.value || '').toLowerCase();
    tblApd.innerHTML = '';

    const statusOrder = { 'Loan': 0, 'Handover': 1, 'Ready': 2 };
    apdCache
      .filter(t => (t.name||'').toLowerCase().includes(q) || (t.code||'').toLowerCase().includes(q))
      .sort((a, b) => {
          const aIsMine = Number(a.holder_id || 0) === USER_ID ? 0 : 1;
          const bIsMine = Number(b.holder_id || 0) === USER_ID ? 0 : 1;
          if (aIsMine !== bIsMine) return aIsMine - bIsMine;
          const sByStatus = (statusOrder[a.current_status] ?? 9) - (statusOrder[b.current_status] ?? 9);
          if (sByStatus !== 0) return sByStatus;
          return (a.name||'').localeCompare(b.name||'');
      })
      .forEach(t => {
          let actionHtml = '';
          const isHolder = Number(t.holder_id || 0) === USER_ID;
          const showHolderName = t.current_status !== 'Ready' && t.holder_name;
          const holderLocationText = showHolderName ? (t.holder_location ? `${t.holder_name} @ ${t.holder_location}` : t.holder_name) : '';

          if (t.current_status === 'Ready') {
              actionHtml = `<button data-act="apd_loan" data-id="${t.id}" class="px-2 py-1 bg-teal-600 text-white text-xs rounded" style="background-color:#0d9488;color:#fff;">Pinjam</button>`;
          } else if (t.current_status === 'Assigned') {
              if (ROLE === 'administrator' || ROLE === 'direktur' || ROLE === 'hse') {
                  actionHtml = `<button data-act="apd_force_return" data-id="${t.id}" class="px-2 py-1 bg-red-600 text-white text-xs rounded whitespace-nowrap">Tarik Kembali</button>`;
              } else {
                  actionHtml = `<span class="text-xs text-gray-500">Milik Teknisi</span>`;
              }
          } else if (t.current_status === 'Loan' || t.current_status === 'Handover') {
              if (ROLE === 'administrator' || ROLE === 'direktur' || ROLE === 'hse') {
                  actionHtml = `<button data-act="apd_force_return" data-id="${t.id}" class="px-2 py-1 bg-red-600 text-white text-xs rounded whitespace-nowrap">Tarik Kembali</button>`;
              } else {
                  actionHtml = `<span class="text-xs text-gray-500">Dipinjam</span>`;
              }
          }

          if (ROLE === 'administrator' || ROLE === 'direktur' || ROLE === 'hse') {
              actionHtml += ` <button data-act="apd_delete" data-id="${t.id}" class="px-2 py-1 bg-red-500 text-white text-xs rounded ml-1">Hapus</button>`;
          }

          const dotColor = t.current_status === 'Ready' ? 'bg-green-500'
                          : t.current_status === 'Assigned' ? 'bg-teal-500'
                          : t.current_status === 'Loan' ? 'bg-yellow-500'
                          : t.current_status === 'Handover' ? 'bg-blue-500'
                          : 'bg-gray-400';

          const tr = document.createElement('tr');
          tr.className = "hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer";
          tr.innerHTML = `
              <td class="px-4 py-2">${escapeHtml(t.name || '')}</td>
              <td class="px-4 py-2">${escapeHtml(t.code || '')}</td>
              <td class="px-4 py-2">
                  ${showHolderName ? `
                    <div class="text-sm font-medium text-gray-800 dark:text-gray-100">${escapeHtml(t.holder_name)}</div>
                    ${t.holder_location ? `<div class="text-xs text-gray-400"><i class="fas fa-map-marker-alt mr-1"></i>${escapeHtml(t.holder_location)}</div>` : ''}
                    ${t.approved_by_name ? `<div class="text-xs text-gray-400"><i class="fas fa-check-circle mr-1"></i>Approved: ${escapeHtml(t.approved_by_name)}</div>` : ''}
                    ${t.holder_start_date ? `<div class="text-xs text-gray-400"><i class="fas fa-calendar mr-1"></i>${new Date(t.holder_start_date).toLocaleDateString('id-ID')}${t.holder_end_date ? ' - ' + new Date(t.holder_end_date).toLocaleDateString('id-ID') : ''}</div>` : ''}
                  ` : `<span class="text-xs text-gray-400">-</span>`}
              </td>
              <td class="px-4 py-2">${actionHtml}</td>
              <td class="px-4 py-2">
                  <div class="inline-flex items-center">
                      <span class="w-3 h-3 rounded-full ${dotColor} mr-2"></span>
                      <span class="text-sm text-gray-700 dark:text-gray-200">${escapeHtml(t.current_status || '')}</span>
                  </div>
              </td>
          `;

          tr.addEventListener('click', (e) => {
              if (e.target.closest('button')) return;
              openToolDetail(t.id);
          });

          tr.querySelectorAll('button[data-act]').forEach(btn => {
              btn.addEventListener('click', (ev) => {
                  ev.stopPropagation();
                  const act = btn.dataset.act;
                  const id = btn.dataset.id;
                  if (act === 'apd_loan') {
                      document.getElementById('apdLoanToolId').value = id;
                      
                      const nowAPD = new Date();
                      const padA = n => String(n).padStart(2, '0');
                      const nowStrAPD = nowAPD.getFullYear() + '-' + padA(nowAPD.getMonth()+1) + '-' + padA(nowAPD.getDate()) + 'T' + padA(nowAPD.getHours()) + ':' + padA(nowAPD.getMinutes());
                      const apdStart = document.getElementById('apdStartDate');
                      const apdEnd = document.getElementById('apdEndDate');
                      if (apdStart) { apdStart.value = nowStrAPD; apdStart.min = nowStrAPD; }
                      if (apdEnd) { apdEnd.min = nowStrAPD; }
                      const selUser = document.getElementById('apdLoanToUser');
                      if (selUser && (ROLE === 'administrator' || ROLE === 'direktur' || ROLE === 'hse')) {
                          fetchJSON(API + '?action=list_technicians').then(techs => {
                              selUser.innerHTML = '<option value="">-- Pilih Staff --</option>';
                              (techs || []).forEach(u => {
                                  const opt = document.createElement('option');
                                  opt.value = u.id; opt.textContent = u.full_name + (u.role === 'sales' ? ' (Sales)' : '');
                                  selUser.appendChild(opt);
                              });
                          });
                      }
                      show($('#modalApdLoan'));
                  }
                  else if (act === 'apd_return') {
                      document.getElementById('apdReturnToolId').value = id;
                      show($('#modalApdReturn'));
                  }
                  else if (act === 'apd_force_return') {
                      if (confirm('Paksa kembalikan APD ini?')) {
                          const fd = new FormData();
                          fd.append('action', 'apd_force_return');
                          fd.append('tool_id', id);
                          fetch(API, { method: 'POST', body: fd }).then(r => toJSON(r)).then(j => {
                              if (j.error) return showToast(j.error, 'error');
                              showToast(j.message || 'APD dikembalikan paksa', 'success');
                              loadApd(true);
                          }).catch(e => showToast(String(e), 'error'));
                      }
                  }
                  else if (act === 'apd_delete') {
                      if (confirm('Hapus APD ini permanen?')) {
                          const fd = new FormData();
                          fd.append('action', 'delete_apd');
                          fd.append('tool_id', id);
                          fetch(API, { method: 'POST', body: fd }).then(r => toJSON(r)).then(j => {
                              if (j.error) return showToast(j.error, 'error');
                              showToast(j.message || 'APD dihapus', 'success');
                              loadApd(true);
                          }).catch(e => showToast(String(e), 'error'));
                      }
                  }
              });
          });

          tblApd.appendChild(tr);
      });
}

function loadApd(force = false) {
    if (!force && apdCache.length) return renderApd();
    fetchJSON(API + '?action=list_apd')
      .then(data => {
          apdCache = Array.isArray(data) ? data : [];
          renderApd();
      })
      .catch(err => {
          console.error('loadApd error', err);
          if (tblApd) tblApd.innerHTML = `<tr><td colspan="5" class="px-4 py-4 text-red-600">Gagal memuat data APD.</td></tr>`;
      });
}


const searchApdInput = $('#searchApd');
if (searchApdInput) {
    searchApdInput.addEventListener('input', () => renderApd());
}


const btnAddApd = $('#btnAddApd');
if (btnAddApd) {
    btnAddApd.addEventListener('click', () => show($('#modalAddApd')));
}


const formAddApd = $('#formAddApd');
if (formAddApd) {
    formAddApd.addEventListener('submit', function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        fd.append('action', 'add_apd');
        fetch(API, { method: 'POST', body: fd }).then(r => toJSON(r)).then(j => {
            if (j.error) return showToast(j.error, 'error');
            showToast(j.message || 'APD ditambahkan', 'success');
            hide($('#modalAddApd'));
            this.reset();
            loadApd(true);
        }).catch(e => showToast(String(e), 'error'));
    });
}


const formApdLoan = $('#formApdLoan');
if (formApdLoan) {
    formApdLoan.addEventListener('submit', function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        fd.append('action', 'apd_request');
        fetch(API, { method: 'POST', body: fd }).then(r => toJSON(r)).then(j => {
            if (j.error) return showToast(j.error, 'error');
            showToast(j.message || 'Permintaan APD dikirim', 'success');
            hide($('#modalApdLoan'));
            this.reset();
            loadApd(true);
        }).catch(e => showToast(String(e), 'error'));
    });
}


const formApdReturn = $('#formApdReturn');
if (formApdReturn) {
    formApdReturn.addEventListener('submit', async function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        fd.append('action', 'apd_return');

        
        const mobileInput = document.querySelector('#modalApdReturn input[name="return_photo_mobile"]');
        const desktopInput = document.getElementById('apd_return_photo_desktop');
        let photoFile = null;
        if (mobileInput && mobileInput.files.length > 0) {
            photoFile = mobileInput.files[0];
        } else if (desktopInput && desktopInput.files.length > 0) {
            photoFile = desktopInput.files[0];
        }
        if (photoFile) {
            const compressed = await compressImage(photoFile);
            fd.append('return_photo', compressed);
        }

        fetch(API, { method: 'POST', body: fd }).then(r => toJSON(r)).then(j => {
            if (j.error) return showToast(j.error, 'error');
            showToast(j.message || 'Permintaan return APD dikirim', 'success');
            hide($('#modalApdReturn'));
            this.reset();
            loadApd(true);
        }).catch(e => showToast(String(e), 'error'));
    });
}


document.querySelectorAll('.apd-return-cam-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const source = this.dataset.source;
        let existingInput = document.querySelector('#modalApdReturn input[name="return_photo_mobile"]');
        if (existingInput) existingInput.remove();

        const inp = document.createElement('input');
        inp.type = 'file';
        inp.name = 'return_photo_mobile';
        inp.accept = 'image/*';
        inp.style.display = 'none';
        if (source === 'camera') inp.setAttribute('capture', 'environment');
        
        inp.addEventListener('change', function() {
            if (this.files.length > 0) {
                const preview = document.getElementById('apdReturnPhotoPreview');
                if (preview) {
                    preview.classList.remove('hidden');
                    preview.querySelector('img').src = URL.createObjectURL(this.files[0]);
                }
            }
        });
        
        document.getElementById('formApdReturn').appendChild(inp);
        inp.click();
    });
});



window._assignApdClick = function() {
    if (!window._currentTechUser) { alert('Error: Data teknisi belum dimuat. Tutup modal dan buka kembali.'); return; }
    const _currentTechUser = window._currentTechUser;
    
    document.getElementById('assignApdUserId').value = _currentTechUser.id;
    document.getElementById('assignApdUserName').value = _currentTechUser.full_name || '';

    
    const sel = document.getElementById('assignApdSelect');
    sel.innerHTML = '<option value="">Memuat...</option>';
    fetch((typeof TOOLS_API_URL !== 'undefined' ? TOOLS_API_URL : 'app/pages/tools.php') + '?action=list_apd')
        .then(r => r.json())
        .then(data => {
            const readyApd = (data || []).filter(a => a.current_status === 'Ready');
            sel.innerHTML = '<option value="">-- Pilih APD yang tersedia --</option>';
            if (readyApd.length === 0) {
                sel.innerHTML = '<option value="">Tidak ada APD tersedia (semua sudah di-assign)</option>';
            } else {
                readyApd.forEach(a => {
                    const opt = document.createElement('option');
                    opt.value = a.id;
                    opt.textContent = a.name + ' (' + a.code + ')';
                    sel.appendChild(opt);
                });
            }
        })
        .catch(() => {
            sel.innerHTML = '<option value="">Gagal memuat data APD</option>';
        });

    
    const techModal = document.getElementById('modalTechTools');
    if (techModal) {
        techModal.classList.remove('flex');
        techModal.classList.add('hidden');
    }

    
    const modal = document.getElementById('modalAssignApd');
    if (modal) {
        modal.classList.add('flex');
        modal.classList.remove('hidden');
        document.documentElement.style.overflow = 'hidden';
    }
};


const formAssignApd = document.getElementById('formAssignApd');
if (formAssignApd) {
    formAssignApd.addEventListener('submit', function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        fd.append('action', 'apd_request');

        fetch((typeof TOOLS_API_URL !== 'undefined' ? TOOLS_API_URL : 'app/pages/tools.php'), { method: 'POST', body: fd })
            .then(r => r.json())
            .then(j => {
                if (j.error) { alert(j.error); return; }
                alert(j.message || 'APD berhasil di-assign ke teknisi');
                
                const modal = document.getElementById('modalAssignApd');
                if (modal) { modal.classList.remove('flex'); modal.classList.add('hidden'); }
                formAssignApd.reset();
                
                const techModal = document.getElementById('modalTechTools');
                if (techModal) { techModal.classList.remove('hidden'); techModal.classList.add('flex'); }
                
                const _currentTechUser = window._currentTechUser;
                if (_currentTechUser) {
                    const tblApdEl = document.querySelector('#tblTechApd');
                    if (tblApdEl) {
                        fetch((typeof TOOLS_API_URL !== 'undefined' ? TOOLS_API_URL : 'app/pages/tools.php') + '?action=list_apd')
                            .then(r => r.json())
                            .then(apdList => {
                                tblApdEl.innerHTML = '';
                                const emptyEl = document.querySelector('#techApdEmpty');
                                const userApd = (apdList || []).filter(a => Number(a.holder_id || 0) === Number(_currentTechUser.id));
                                if (userApd.length > 0) {
                                    if (emptyEl) emptyEl.classList.add('hidden');
                                    userApd.forEach(a => {
                                        const tr = document.createElement('tr');
                                        tr.className = 'hover:bg-gray-50 dark:hover:bg-gray-800';
                                        const dotColor = a.current_status === 'Ready' ? 'bg-green-500'
                                                        : a.current_status === 'Assigned' ? 'bg-teal-500'
                                                        : a.current_status === 'Loan' ? 'bg-yellow-500'
                                                        : a.current_status === 'Handover' ? 'bg-blue-500'
                                                        : 'bg-gray-400';
                                        tr.innerHTML = `
                                          <td class="px-4 py-2">${a.name || ''}</td>
                                          <td class="px-4 py-2">${a.code || ''}</td>
                                          <td class="px-4 py-2">
                                            <span class="inline-flex items-center">
                                              <span class="w-3 h-3 rounded-full ${dotColor} mr-2"></span>
                                              <span class="text-sm">${a.current_status || ''}</span>
                                            </span>
                                          </td>`;
                                        tblApdEl.appendChild(tr);
                                    });
                                } else {
                                    if (emptyEl) emptyEl.classList.remove('hidden');
                                }
                            });
                    }
                }
            })
            .catch(e => alert(String(e)));
    });
}


document.querySelectorAll('[data-close="modalAssignApd"]').forEach(btn => {
    btn.addEventListener('click', () => {
        const modal = document.getElementById('modalAssignApd');
        if (modal) { modal.classList.remove('flex'); modal.classList.add('hidden'); }
        
        const techModal = document.getElementById('modalTechTools');
        if (techModal) { techModal.classList.remove('hidden'); techModal.classList.add('flex'); }
        if (!document.querySelector('.fixed:not(.hidden)')) { document.documentElement.style.overflow = ''; }
    });
});
  
});