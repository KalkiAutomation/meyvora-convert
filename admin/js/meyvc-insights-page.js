(function ($) {
	"use strict";

	function parseNum(text) {
		var n = parseFloat(String(text).replace(/[^0-9.\-]/g, ""));
		return isNaN(n) ? 0 : n;
	}

	$(function () {
		var $table = $(".js-meyvc-campaign-compare");
		if (!$table.length) {
			return;
		}
		var $tbody = $table.find("tbody");
		var $headers = $table.find("thead th[data-sort-key]");

		$headers.on("click", function () {
			var $th = $(this);
			var key = $th.data("sort-key");
			var type = $th.data("sort-type") || "number";
			var cur = $th.attr("data-sort-dir") === "asc" ? "asc" : "desc";
			var next = cur === "asc" ? "desc" : "asc";
			$headers.removeAttr("data-sort-dir");
			$th.attr("data-sort-dir", next);

			var rows = $tbody.find("tr").get();
			rows.sort(function (a, b) {
				var $a = $(a).find('td[data-sort="' + key + '"]');
				var $b = $(b).find('td[data-sort="' + key + '"]');
				var va =
					type === "text"
						? $a.data("sortRaw") || $a.text()
						: parseNum($a.data("sortRaw"));
				var vb =
					type === "text"
						? $b.data("sortRaw") || $b.text()
						: parseNum($b.data("sortRaw"));
				if (type === "text") {
					return next === "asc"
						? String(va).localeCompare(String(vb))
						: String(vb).localeCompare(String(va));
				}
				return next === "asc" ? va - vb : vb - va;
			});
			$.each(rows, function (_, row) {
				$tbody.append(row);
			});
		});
	});
})(jQuery);
