(function () {
	var page      = document.getElementById('rm-dl-page');
	if (!page) return;

	var results   = document.getElementById('rm-dl-results');
	var productGrp= document.getElementById('rm-dl-product-group');
	var productSel= document.getElementById('rm-dl-product-select');
	var typeCbs   = Array.from(page.querySelectorAll('.rm-dl-type-cb'));

	var manualTypes    = ['catalogue','brochure','education','3d','revit','bim'];
	var productTypes   = ['datasheet','installation','ldt'];
	var ajaxPending    = null;

	function selectedTypes() {
		return typeCbs.filter(function(cb){ return cb.checked; }).map(function(cb){ return cb.value; });
	}

	function hasProductType(types) {
		return types.some(function(t){ return productTypes.indexOf(t) !== -1; });
	}

	function manualHtml(types) {
		var entries = (window.rmDlManual || []).filter(function(e){
			return types.length === 0 || types.indexOf(e.type) !== -1;
		});
		if (!entries.length) return '';
		var html = '<div class="rm-dl-grid">';
		entries.forEach(function(e) {
			var ext = (e.url.split('.').pop() || '').toUpperCase();
			var thumb = e.thumb
				? '<div class="rm-dl-card-thumb"><img src="'+e.thumb+'" alt="'+e.title+'" loading="lazy"></div>'
				: '<div class="rm-dl-card-thumb rm-dl-card-thumb--icon"><span class="rm-dl-ext">'+ext+'</span></div>';
			html += '<a class="rm-dl-card" href="'+e.url+'" download rel="noopener" data-type="'+e.type+'">'
				+ thumb
				+ '<div class="rm-dl-card-body">'
				+ '<span class="rm-dl-card-title">'+e.title+'</span>'
				+ '<span class="rm-dl-card-meta">'+e.type+(ext?' · '+ext:'')+'</span>'
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
		var types    = selectedTypes();
		var manual   = manualTypes.filter(function(t){ return types.indexOf(t) !== -1; });
		var product  = productTypes.filter(function(t){ return types.indexOf(t) !== -1; });
		var needsProd = product.length > 0;

		// Product dropdown always visible.

		if (!types.length) {
			results.innerHTML = '<p class="rm-dl-empty">Select a filter above to browse files.</p>';
			return;
		}

		var manHtml = manualHtml(manual);

		if (!needsProd) {
			results.innerHTML = manHtml || '<p class="rm-dl-empty">No files found for the selected filters.</p>';
			return;
		}

		// Show manual immediately, then append product files.
		results.innerHTML = manHtml + '<div class="rm-dl-loading">Loading&hellip;</div>';

		fetchProductFiles(product, productSel.value, function(html) {
			var loader = results.querySelector('.rm-dl-loading');
			if (loader) loader.remove();
			if (html) results.insertAdjacentHTML('beforeend', html);
			if (!results.querySelector('.rm-dl-card')) {
				results.innerHTML = '<p class="rm-dl-empty">No files found for the selected filters.</p>';
			}
		});
	}

	typeCbs.forEach(function(cb){ cb.addEventListener('change', update); });
	productSel.addEventListener('change', update);
})();
