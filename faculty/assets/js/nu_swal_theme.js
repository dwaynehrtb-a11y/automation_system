(function(){
  if(!window.Swal) return;
  const boundOriginalFire = Swal.fire.bind(Swal);
  const nuDefaults = {
    background: '#0c1e3d',
    color: '#f5f7fa',
    buttonsStyling: false,
    confirmButtonText: 'OK',
    customClass: {
      popup: 'nu-swal-popup',
      title: 'nu-swal-title',
      htmlContainer: 'nu-swal-html',
      confirmButton: 'nu-swal-confirm',
      cancelButton: 'nu-swal-cancel',
      icon: 'nu-swal-icon'
    }
  };
  Swal.fire = function(opts){
    if(typeof opts === 'string') {
      return boundOriginalFire({...nuDefaults, text: opts});
    }
    const merged = {
      ...nuDefaults,
      ...(opts || {}),
      customClass: {
        ...nuDefaults.customClass,
        ...(opts && opts.customClass ? opts.customClass : {})
      }
    };
    return boundOriginalFire(merged);
  };
})();
