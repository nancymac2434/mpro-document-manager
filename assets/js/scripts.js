// ===== Select2 & "Select All" toggle (scoped to each .mpro-selectbox-block) =====
jQuery(function($){
  // Initialize any uninitialized .mpro-user-select with Select2
  function initSelect2($scope){
	$scope.find('select.mpro-user-select').each(function(){
	  var $sel = $(this);
	  if ($.fn.select2 && !$sel.hasClass('select2-hidden-accessible')) {
		$sel.select2({ width: '100%' });
	  }
	});
  }

  // On page load
  initSelect2($(document));

  // If you dynamically add blocks later, call initSelect2(newContainer)

  // Toggle helpers (per container)
  function totalOptions($sel){ return $sel.find('option').length; }
  function selectedCount($sel){ return ($sel.val() || []).length; }

  // Click handler for "Select All ..." buttons (delegated)
  $(document).on('click', '.mpro-selectbox-block .mpro-select-all', function(){
	var $box = $(this).closest('.mpro-selectbox-block');
	var $sel = $box.find('select.mpro-user-select');
	var $all = $box.find('input.mpro-all-flag');
	var label = $(this).text();

	if (selectedCount($sel) === totalOptions($sel) && totalOptions($sel) > 0) {
	  // Clear all
	  $sel.val(null).trigger('change');
	  $all.val('0');
	  // swap label text (kept simple; you can customize if needed)
	  $(this).text(label.replace(/^Clear All/i, 'Select All'));
	} else {
	  // Select all
	  var allVals = $sel.find('option').map(function(){ return this.value; }).get();
	  $sel.val(allVals).trigger('change');
	  $all.val('1');
	  $(this).text(label.replace(/^Select All/i, 'Clear All'));
	}
  });

  // Keep hidden flag + button label synced if user manually edits selection
  $(document).on('change', '.mpro-selectbox-block select.mpro-user-select', function(){
	var $box = $(this).closest('.mpro-selectbox-block');
	var $sel = $(this);
	var $all = $box.find('input.mpro-all-flag');
	var $btn = $box.find('button.mpro-select-all');

	if (selectedCount($sel) === totalOptions($sel) && totalOptions($sel) > 0) {
	  $all.val('1');
	  $btn.each(function(){
		var t = $(this).text();
		$(this).text(t.replace(/^Select All/i, 'Clear All'));
	  });
	} else {
	  $all.val('0');
	  $btn.each(function(){
		var t = $(this).text();
		$(this).text(t.replace(/^Clear All/i, 'Select All'));
	  });
	}
  });
});


// ===== Tab switching =====
jQuery(function ($) {
  $(document).on('click', '.mpro-doc-tabs .mpro-tab-nav li', function (e) {
	e.preventDefault();
	const $li = $(this);
	const tabKey = $li.data('tab'); // 'uploaded' | 'shared' | 'shared-direct'
	const $wrap = $li.closest('.mpro-doc-tabs');
	if (!tabKey || !$wrap.length) return;

	const targetId = '#tab-' + tabKey;
	const $target  = $wrap.find(targetId);
	if (!$target.length) return;

	$li.addClass('active').attr('aria-selected', 'true')
	   .siblings().removeClass('active').attr('aria-selected', 'false');

	$wrap.find('.mpro-tab-content').removeClass('active');
	$target.addClass('active');
  });

  // Ensure exactly one panel is active on load
  $('.mpro-doc-tabs').each(function () {
	const $wrap = $(this);
	if ($wrap.find('.mpro-tab-content.active').length === 0) {
	  $wrap.find('.mpro-tab-content').first().addClass('active');
	  $wrap.find('.mpro-tab-nav li').first().addClass('active').attr('aria-selected', 'true');
	}
  });
});


// ===== Drag & drop upload (as you had) =====
jQuery(function($){
  var $drop   = $('#mpro-dropzone');
  var $browse = $('#mpro-browse-btn');
  var $input  = $('#document_file');
  var $prev   = $('#mpro-preview');

  var MAX_BYTES = 5 * 1024 * 1024;
  var allowedExt = ['jpg','jpeg','gif','png','doc','docx','pdf','ppt','pptx','xls','xlsx'];

  function extOf(name){
	var i = name.lastIndexOf('.');
	return i >= 0 ? name.slice(i+1).toLowerCase() : '';
  }
  function showError(msg){
	if (window.Swal) { Swal.fire({icon:'error', title:'Upload error', text: msg}); }
	else { alert(msg); }
  }
  function setFile(file){
	var ext = extOf(file.name);
	if (allowedExt.indexOf(ext) === -1) { showError('"' + file.name + '" is not an allowed file type.'); return; }
	if (file.size > MAX_BYTES) { showError('"' + file.name + '" exceeds 5MB.'); return; }

	var dt = new DataTransfer();
	dt.items.add(file);
	$input[0].files = dt.files;

	$prev.empty();
	var $chip = $('<span class="mpro-file-chip"></span>');
	if (file.type.startsWith('image/')) {
	  var img = document.createElement('img');
	  img.alt = '';
	  var reader = new FileReader();
	  reader.onload = function(e){ img.src = e.target.result; };
	  reader.readAsDataURL(file);
	  $chip.append(img);
	}
	$chip.append($('<span/>').text(file.name + ' • ' + Math.ceil(file.size/1024) + ' KB'));
	var $clear = $('<button type="button" aria-label="Remove file">×</button>').css({
	  background:'none', border:'none', fontSize:'16px', lineHeight:'1', cursor:'pointer', color:'#2B4D59'
	}).on('click', function(){
	  var dt2 = new DataTransfer();
	  $input[0].files = dt2.files;
	  $prev.empty();
	});
	$chip.append($clear);
	$prev.append($chip);
  }
  function handleFiles(fileList){
	if (!fileList || !fileList.length) return;
	setFile(fileList[0]);
  }
  $drop.on('drag dragstart dragend dragover dragenter dragleave drop', function(e){
	e.preventDefault(); e.stopPropagation();
  });
  $drop.on('dragover dragenter', function(){ $drop.addClass('mpro-dragover'); });
  $drop.on('dragleave dragend drop', function(){ $drop.removeClass('mpro-dragover'); });
  $drop.on('drop', function(e){
	var files = e.originalEvent.dataTransfer.files;
	handleFiles(files);
  });
  $drop.on('click', function(){ $input.trigger('click'); });
  $drop.on('keydown', function(e){ if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); $input.trigger('click'); }});
  $browse.on('click', function(e){ e.preventDefault(); $input.trigger('click'); });
  $input.on('change', function(e){ handleFiles(e.target.files); });
});


// ===== Per-tab search (DataTables-aware) =====
jQuery(function($){
  var pairs = [
	{ input: '#search-uploaded',      table: '#uploaded-table' },
	{ input: '#search-shared',        table: '#shared-table' },
	{ input: '#search-shared-direct', table: '#shared-direct-table' }
  ];

  function simpleFilter($table, term){
	term = (term || '').toLowerCase();
	var $rows = $table.find('tbody tr');
	if (!term) { $rows.show(); return; }
	$rows.each(function(){
	  var text = $(this).text().toLowerCase();
	  $(this).toggle(text.indexOf(term) !== -1);
	});
  }

  pairs.forEach(function(pair){
	var $inp = $(pair.input);
	var $tbl = $(pair.table);
	if (!$inp.length || !$tbl.length) return;

	var dt = $.fn.DataTable && $.fn.DataTable.isDataTable(pair.table)
	  ? $(pair.table).DataTable()
	  : null;

	$inp.on('input', function(){
	  var term = $(this).val();
	  if (dt) dt.search(term).draw();
	  else simpleFilter($tbl, term);
	});
  });

  // (Optional) clear the active tab's search when switching tabs
  $(document).on('click', '.mpro-tab-nav [data-tab]', function(){
	// Uncomment to clear on tab switch:
	// var tab = $(this).attr('data-tab');
	// var map = { uploaded:'#search-uploaded', shared:'#search-shared', 'shared-direct':'#search-shared-direct' };
	// var sel = map[tab];
	// if (sel) $(sel).val('').trigger('input');
  });
});

// ===== DataTables init (idempotent) =====
jQuery(function($){
  function initDT(sel){
	if (!$.fn.DataTable) return;
	var $t = $(sel);
	if (!$t.length) return;
	//if ($.fn.DataTable.isDataTable($t)) return;
    // If previously initialized, destroy before reinit with new options
	  if ($.fn.DataTable.isDataTable($t)) {
		$t.DataTable().destroy();
	  }

	$t.DataTable({
	  order: [[1, "desc"]],
	  columnDefs: [{ orderable: true, targets: [0,1] }],
	  paging: false,
	  info: false,
	  dom: 't'   // (table only). Alternative: 'lrt' if you want the length selector.
	});
  }

  initDT('#uploaded-table');
  initDT('#shared-table');
  initDT('#shared-direct-table');

  $(document).on('click', '.mpro-tab-nav [data-tab]', function(){
	var tab = $(this).attr('data-tab');
	if (tab === 'uploaded') initDT('#uploaded-table');
	else if (tab === 'shared') initDT('#shared-table');
	else if (tab === 'shared-direct') initDT('#shared-direct-table');
  });
});
