document.addEventListener('contextmenu', function(e) {
	if (e.target.tagName === 'IMG') {
		e.preventDefault();
	}
});
document.addEventListener('dragstart', function(e) {
	if (e.target.tagName === 'IMG') {
		e.preventDefault();
	}
});
