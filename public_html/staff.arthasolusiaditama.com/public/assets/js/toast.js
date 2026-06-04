 
(function() {
  if (window.showToast) return;

  
  if (!document.getElementById('global-toast-css')) {
    var s = document.createElement('style');
    s.id = 'global-toast-css';
    s.textContent =
      '@keyframes gtoast-in{0%{transform:scale(.3);opacity:0}50%{transform:scale(1.05)}70%{transform:scale(.95)}100%{transform:scale(1);opacity:1}}' +
      '@keyframes gtoast-out{from{opacity:1;transform:scale(1)}to{opacity:0;transform:scale(.8)}}' +
      '@keyframes gtoast-circle{from{stroke-dashoffset:166}to{stroke-dashoffset:0}}' +
      '@keyframes gtoast-check{from{stroke-dashoffset:48}to{stroke-dashoffset:0}}' +
      '@keyframes gtoast-x{from{stroke-dashoffset:20}to{stroke-dashoffset:0}}' +
      '@keyframes gtoast-fill{from{opacity:0;transform:scale(0)}to{opacity:.15;transform:scale(1)}}' +
      '.gtoast-overlay{position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.3);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px)}' +
      '.gtoast-card{background:#fff;border-radius:1.25rem;padding:2rem 1.5rem 1.5rem;text-align:center;min-width:260px;max-width:320px;box-shadow:0 25px 50px -12px rgba(0,0,0,.25);animation:gtoast-in .5s cubic-bezier(.175,.885,.32,1.275)}' +
      '.gtoast-card.closing{animation:gtoast-out .3s ease forwards}' +
      '.gtoast-icon{width:80px;height:80px;margin:0 auto 1rem;position:relative}' +
      '.gtoast-circ{fill:none;stroke-width:3;stroke-linecap:round;stroke-dasharray:166;stroke-dashoffset:166;animation:gtoast-circle .6s .1s cubic-bezier(.65,0,.45,1) forwards}' +
      '.gtoast-chk{fill:none;stroke:#fff;stroke-width:3.5;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:48;stroke-dashoffset:48;animation:gtoast-check .35s .5s cubic-bezier(.65,0,.45,1) forwards}' +
      '.gtoast-xl{fill:none;stroke:#fff;stroke-width:3.5;stroke-linecap:round;stroke-dasharray:20;stroke-dashoffset:20;animation:gtoast-x .3s .5s cubic-bezier(.65,0,.45,1) forwards}' +
      '.gtoast-bg{transform-origin:center;animation:gtoast-fill .4s .4s ease forwards;opacity:0}' +
      '.dark .gtoast-card{background:#1f2937;color:#e5e7eb}';
    document.head.appendChild(s);
  }

  window.showToast = function(message, type) {
    type = type || 'info';
    var isMain = (type === 'success' || type === 'error');
    if (!isMain) {
      var colors = { warning:'#eab308', info:'#3b82f6' };
      var icons = { warning:'<i class="fas fa-exclamation-triangle"></i>', info:'<i class="fas fa-info-circle"></i>' };
      var bar = document.createElement('div');
      bar.style.cssText = 'position:fixed;top:1rem;left:1rem;right:1rem;z-index:99999;display:flex;align-items:center;gap:.75rem;padding:.75rem 1rem;border-radius:.75rem;color:#fff;font-size:.875rem;font-weight:500;box-shadow:0 10px 25px rgba(0,0,0,.15);transform:translateY(-120%);transition:transform .3s;background:' + (colors[type]||colors.info);
      bar.innerHTML = '<span style="font-size:1.2rem">' + (icons[type]||icons.info) + '</span><span style="flex:1">' + message + '</span>';
      document.body.appendChild(bar);
      requestAnimationFrame(function(){ bar.style.transform='translateY(0)'; });
      setTimeout(function(){ bar.style.transform='translateY(-120%)'; setTimeout(function(){ bar.remove(); },300); },3000);
      return;
    }
    var overlay = document.createElement('div');
    overlay.className = 'gtoast-overlay';
    var c = type==='success' ? '#22c55e' : '#ef4444';
    var svg = type==='success'
      ? '<circle class="gtoast-bg" cx="26" cy="26" r="25" fill="'+c+'"/><circle class="gtoast-circ" cx="26" cy="26" r="25" stroke="'+c+'"/><path class="gtoast-chk" d="M14 27l8 8 16-16"/>'
      : '<circle class="gtoast-bg" cx="26" cy="26" r="25" fill="'+c+'"/><circle class="gtoast-circ" cx="26" cy="26" r="25" stroke="'+c+'"/><line class="gtoast-xl" x1="18" y1="18" x2="34" y2="34"/><line class="gtoast-xl" x1="34" y1="18" x2="18" y2="34" style="animation-delay:.6s"/>';
    overlay.innerHTML = '<div class="gtoast-card"><div class="gtoast-icon"><svg viewBox="0 0 52 52" width="80" height="80">' + svg + '</svg></div>' +
      '<p style="font-size:1rem;font-weight:700;color:' + (type==='success'?'#16a34a':'#dc2626') + ';margin-bottom:.25rem">' + (type==='success'?'Berhasil!':'Gagal!') + '</p>' +
      '<p style="font-size:.875rem;color:#6b7280">' + message + '</p></div>';
    document.body.appendChild(overlay);
    overlay.addEventListener('click', function(e){ if(e.target===overlay) close(); });
    function close(){
      var card = overlay.querySelector('.gtoast-card');
      if(card) card.classList.add('closing');
      overlay.style.opacity='0'; overlay.style.transition='opacity .3s';
      setTimeout(function(){ overlay.remove(); },300);
    }
    setTimeout(close, 2500);
  };

  window.showHeaderToast = window.showToast;
})();
