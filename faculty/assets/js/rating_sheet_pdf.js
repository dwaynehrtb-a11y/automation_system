// rating_sheet_pdf.js
// Builds a printable Rating Sheet and downloads as PDF using html2pdf.
(function(){
  window.downloadRatingSheetPDF = async function(){
    const classSelect = document.querySelector('select[name="class_select"], select[id="class_select"], select[data-class-select]');
    const classCode = classSelect ? classSelect.value : '';
    if(!classCode){
      Swal.fire({title:'Select a Class', text:'Please choose a class first.', icon:'info'});
      return;
    }
    try {
      const resp = await fetch('../ajax/get_rating_sheet_data.php?class_code='+encodeURIComponent(classCode));
      const data = await resp.json();
      if(!data.success){
        Swal.fire({title:'Error', text:data.message||'Failed to load data.', icon:'error'}); return;
      }
      const wrap = document.createElement('div');
      wrap.className='rating-sheet-pdf';
      const c = data.class;
      wrap.innerHTML = `
      <style>
        .rating-sheet-pdf { font-family: Arial, sans-serif; color:#1f2937; }
        .rs-header { text-align:center; background:#003082; color:#fff; padding:12px 10px; border-radius:8px 8px 0 0; }
        .rs-header h2 { margin:0; font-size:18px; letter-spacing:.5px; }
        .rs-meta { text-align:center; font-size:11px; margin:6px 0 14px; }
        table.rs-table { width:100%; border-collapse:collapse; font-size:11px; }
        table.rs-table th { background:#003082; color:#fff; padding:6px 4px; }
        table.rs-table td { padding:5px 4px; border:1px solid #e5e7eb; text-align:center; }
        table.rs-table td.name { text-align:left; }
        table.rs-table tr:nth-child(even) { background:#f8fafc; }
        .status-PASSED { background:#059669; color:#fff; font-weight:bold; }
        .status-FAILED { background:#dc2626; color:#fff; font-weight:bold; }
        .status-INC { background:#d97706; color:#fff; font-weight:bold; }
        .rs-summary { margin-top:14px; font-size:11px; text-align:center; padding:6px; background:#fde68a; border-radius:6px; }
      </style>
      <div class='rs-header'><h2>NU RATING SHEET - ${c.course_code} (${classCode})</h2></div>
      <div class='rs-meta'>Section: ${c.section} | AY: ${c.academic_year} | Term: ${c.term} | Generated: ${new Date().toLocaleString()}</div>
      <table class='rs-table'>
        <thead><tr><th>Student ID</th><th>Name</th><th>Midterm %</th><th>Finals %</th><th>Term %</th><th>Status</th><th>Discrete</th><th>Lacking Req</th><th>Frozen</th><th>Encrypted</th></tr></thead>
        <tbody>
        ${data.rows.map(r=>`<tr>
          <td>${r.student_id}</td>
          <td class='name'>${r.name}</td>
          <td>${r.midterm_percentage}</td>
          <td>${r.finals_percentage}</td>
          <td>${r.term_percentage}</td>
          <td class='status-${r.grade_status}'>${r.grade_status}</td>
          <td>${r.term_grade}</td>
          <td>${r.lacking_requirements||''}</td>
          <td>${r.frozen}</td>
          <td>${r.encrypted}</td>
        </tr>`).join('')}
        </tbody>
      </table>
      <div class='rs-summary'>PASSED: ${data.summary.passed} | FAILED: ${data.summary.failed} | INC: ${data.summary.inc} | ENCRYPTED: ${data.summary.encrypted} | TOTAL: ${data.summary.total}</div>
      `;
      document.body.appendChild(wrap);
      const opt = {
        margin:       6,
        filename:     'NU_Rating_Sheet_'+classCode+'_'+new Date().toISOString().slice(0,19).replace(/[:T]/g,'_')+'.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2 },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
      };
      await html2pdf().set(opt).from(wrap).save();
      wrap.remove();
    } catch(e){
      Swal.fire({title:'Error', text:e.message, icon:'error'});
    }
  };
})();
