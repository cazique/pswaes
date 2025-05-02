AppGiniPlugin = AppGiniPlugin || {};
AppGiniPlugin.charts = {
	hbar: (label = [], val = [], formatVals, valClass = 'primary') => {
		if(!label.length)
			return console.error('AppGiniPlugin.charts.hbar(): label must be a non-empty array');
		if(!val.length)
			return console.error('AppGiniPlugin.charts.hbar(): val must be a non-empty array');

		if(typeof(formatVals) != 'function') formatVals = (i) => i;

		const id = random_string(10);

		const maxVal = Math.max(...val), minVal = Math.min(...val);
		// TODO: we'll assume for now that minVal is +ve ... later
		// we should handle negatives

		const container = $j('<table class="charts charts-hbar"></table>');
		container.attr('id', id);
		
		for(let i = 0; i < label.length; i++) {
			// templates
			const tr = $j('<tr class="charts-entry"></tr>'),
			      tdLabel = $j('<td class="charts-label"></td>'),
			      tdVal = $j('<td class="charts-value"></td>'),
			      divBar = $j(`<div class="charts-value-hbar bg-${valClass} text-${valClass}"></div>`);
		
			const y = val[i] ?? 0;

			tr.attr('title', `${label[i]}: ${formatVals(y)}`);

			tdLabel
				.text(label[i])
				.toggleClass('first-label', !i)
				.appendTo(tr);

			divBar
				.css('width', `${Math.round(y / maxVal * 98)}%`)
				.text(formatVals(y))
				.appendTo(tdVal);

			tdVal
				.toggleClass('first-value', !i)
				.appendTo(tr);
			tr.appendTo(container);
		}

		return container;
	}
}