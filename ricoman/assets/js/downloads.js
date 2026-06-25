(function () {
	var page      = document.getElementById('rm-dl-page');
	if (!page) return;

	var results    = document.getElementById('rm-dl-results');
	var productSel = document.getElementById('rm-dl-product-select'); // hidden input
	var productSearch = document.getElementById('rm-dl-product-search');
	var productList   = document.getElementById('rm-dl-product-list');
	var typeCbs    = Array.from(page.querySelectorAll('.rm-dl-type-cb'));

	var manualTypes  = ['catalogue','brochure','education','3d','revit','bim'];
	var productTypes = ['datasheet','installation','ldt'];
	var imageTypes   = ['catalogue','brochure','education'];
	var ajaxPending  = null;

	var typeLabels = {
		catalogue:'Catalogue', brochure:'Product Literature', education:'Education Guides',
		'3d':'3D Models', revit:'Revit Files', bim:'BIM Files',
		installation:'Installation Instructions', ldt:'LDT Files', datasheet:'Datasheet'
	};

	// Product search — filter list items as user types.
	function filterProductList() {
		var q = productSearch ? productSearch.value.toLowerCase().trim() : '';
		var items = productList ? Array.from(productList.querySelectorAll('.rm-dl-product-opt')) : [];
		items.forEach(function(li) {
			if (li.classList.contains('rm-dl-product-opt--all')) {
				li.style.display = '';
				return;
			}
			li.style.display = (!q || li.textContent.toLowerCase().indexOf(q) !== -1) ? '' : 'none';
		});
	}

	function selectProduct(li) {
		var items = productList ? Array.from(productList.querySelectorAll('.rm-dl-product-opt')) : [];
		items.forEach(function(i){ i.classList.remove('is-active'); });
		li.classList.add('is-active');
		var val = li.dataset.value;
		if (productSel) productSel.value = val;
		if (productSearch) productSearch.value = val ? li.textContent : '';
		update();
	}

	if (productList) {
		productList.addEventListener('click', function(e) {
			var li = e.target.closest('.rm-dl-product-opt');
			if (li) selectProduct(li);
		});
		productList.addEventListener('keydown', function(e) {
			var li = e.target.closest('.rm-dl-product-opt');
			if (!li) return;
			if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); selectProduct(li); }
		});
	}

	if (productSearch) {
		productSearch.addEventListener('input', filterProductList);
		productSearch.addEventListener('focus', function() {
			if (productList) productList.style.display = '';
		});
		productSearch.addEventListener('keydown', function(e) {
			if (e.key === 'Escape') {
				if (productList) productList.style.display = 'none';
				productSearch.blur();
			}
		});
	}

	// Close list when clicking outside.
	document.addEventListener('click', function(e) {
		if (!productList) return;
		var wrap = document.querySelector('.rm-dl-product-search-wrap');
		if (wrap && !wrap.contains(e.target)) productList.style.display = 'none';
	});

	function selectedTypes() {
		return typeCbs.filter(function(cb){ return cb.checked; }).map(function(cb){ return cb.value; });
	}

	function cardThumb(e) {
		if (imageTypes.indexOf(e.type) !== -1 && e.thumb) {
			return '<div class="rm-dl-card-thumb"><img src="'+e.thumb+'" alt="'+e.title+'" loading="lazy"></div>';
		}
		return '<div class="rm-dl-card-thumb rm-dl-card-thumb--icon rm-dl-card-thumb--'+e.type+'"></div>';
	}

	function manualHtml(types) {
		var entries = (window.rmDlManual || []).filter(function(e){
			return types.length === 0 || types.indexOf(e.type) !== -1;
		});
		if (!entries.length) return '';
		var html = '<div class="rm-dl-grid">';
		entries.forEach(function(e) {
			var ext   = (e.url.split('.').pop() || '').toUpperCase();
			var label = typeLabels[e.type] || e.type;
			html += '<a class="rm-dl-card" href="'+e.url+'" download rel="noopener" data-type="'+e.type+'">'
				+ cardThumb(e)
				+ '<div class="rm-dl-card-body">'
				+ '<span class="rm-dl-card-title">'+e.title+'</span>'
				+ '<span class="rm-dl-card-meta">'+label+(ext?' · '+ext:'')+'</span>'
				+ '</div>'
				+ '<span class="rm-dl-card-dl">&darr; Download</span>'
				+ '</a>';
		});
		html += '</div>';
		return html;
	}

	function fetchProductFiles(types, productId, callback) {
		if (ajaxPending) ajaxPending.abort();
		var data = new FormData();
		data.append('action', 'rm_dl_product');
		data.append('nonce',  window.rmDlAjax.nonce);
		data.append('product', productId || '');
		types.forEach(function(t){ data.append('types[]', t); });
		var xhr = new XMLHttpRequest();
		xhr.open('POST', window.rmDlAjax.url);
		xhr.onload = function() {
			try {
				var res = JSON.parse(xhr.responseText);
				callback(res.success ? res.data.html : '');
			} catch(e) { callback(''); }
		};
		xhr.onerror = function(){ callback(''); };
		xhr.send(data);
		ajaxPending = xhr;
	}

	function update() {
		var types      = selectedTypes();
		var manual     = manualTypes.filter(function(t){ return types.indexOf(t) !== -1; });
		var product    = productTypes.filter(function(t){ return types.indexOf(t) !== -1; });
		var productId  = productSel ? productSel.value : '';
		var hasProduct = productId !== '';

		if (!types.length) {
			results.innerHTML = '<p class="rm-dl-empty">Select a filter above to browse files.</p>';
			return;
		}

		// Hide manual files when a product is selected OR when any product-type filter is active.
		var manHtml = (hasProduct || product.length > 0) ? '' : manualHtml(manual);

		if (!product.length) {
			results.innerHTML = manHtml || '<p class="rm-dl-empty">No files found for the selected filters.</p>';
			return;
		}

		results.innerHTML = manHtml + '<div class="rm-dl-loading">Loading&hellip;</div>';

		fetchProductFiles(product, productId, function(html) {
			var loader = results.querySelector('.rm-dl-loading');
			if (loader) loader.remove();
			if (html) results.insertAdjacentHTML('beforeend', html);
			if (!results.querySelector('.rm-dl-card')) {
				results.innerHTML = '<p class="rm-dl-empty">No files found for the selected filters.</p>';
			}
		});
	}

	typeCbs.forEach(function(cb){ cb.addEventListener('change', update); });

	// Run on load to respect the default checked state.
	update();
})();
