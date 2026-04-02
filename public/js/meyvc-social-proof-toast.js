/**
 * Recent purchase toast — rotates through recent orders every N seconds.
 * Optional “viewing now” counter when meyvcViewingCounter is localized.
 *
 * @package Meyvora_Convert
 */
(function () {
	'use strict';
	if (typeof meyvcViewingCounter !== 'undefined' && meyvcViewingCounter) {
		var el = document.getElementById('meyvc-viewing-counter');
		if (el) {
			var min = parseInt(meyvcViewingCounter.min, 10) || 2;
			var max = parseInt(meyvcViewingCounter.max, 10) || 9;
			var tmpl = meyvcViewingCounter.tmpl || '%d';
			function rand(a, b) {
				return Math.floor(Math.random() * (b - a + 1)) + a;
			}
			function update() {
				var n = rand(min, max);
				el.textContent = String(tmpl).replace('%d', String(n));
				el.style.display = '';
			}
			function schedule() {
				setTimeout(function () {
					update();
					schedule();
				}, rand(45000, 90000));
			}
			update();
			schedule();
		}
	}
})();

(function () {
	'use strict';

	if (typeof meyvc_social_proof === 'undefined' || !meyvc_social_proof.purchases || !meyvc_social_proof.purchases.length) {
		return;
	}

	var purchases = meyvc_social_proof.purchases;
	var delay = parseInt(meyvc_social_proof.initial_delay, 10) || 8000;
	var interval = parseInt(meyvc_social_proof.interval, 10) || 12000;
	var idx = 0;

	function createToast() {
		var wrap = document.createElement('div');
		wrap.id = 'meyvc-sp-toast';
		wrap.setAttribute('role', 'status');
		wrap.setAttribute('aria-live', 'polite');
		wrap.style.cssText = [
			'position:fixed',
			'bottom:24px',
			'left:24px',
			'z-index:99990',
			'background:var(--meyvc-toast-bg,#fff)',
			'border:1px solid rgba(0,0,0,.1)',
			'border-radius:8px',
			'padding:10px 14px',
			'font-size:13px',
			'line-height:1.4',
			'max-width:280px',
			'box-shadow:0 2px 8px rgba(0,0,0,.12)',
			'transition:opacity .3s,transform .3s',
			'opacity:0',
			'transform:translateY(8px)',
			'pointer-events:none',
		].join(';');
		document.body.appendChild(wrap);
		return wrap;
	}

	function show(toast, purchase) {
		var tmpl = meyvc_social_proof.toast_template || '{name} from {location} just bought {product}';
		toast.textContent = tmpl
			.replace('{name}', purchase.name)
			.replace('{location}', purchase.location)
			.replace('{product}', purchase.product);
		toast.style.opacity = '1';
		toast.style.transform = 'translateY(0)';

		setTimeout(function () {
			toast.style.opacity = '0';
			toast.style.transform = 'translateY(8px)';
		}, 5000);
	}

	function next(toast) {
		show(toast, purchases[idx]);
		idx = (idx + 1) % purchases.length;
	}

	setTimeout(function () {
		if (!purchases.length) {
			return;
		}
		var toast = createToast();
		next(toast);
		setInterval(function () {
			next(toast);
		}, interval);
	}, delay);
}());
