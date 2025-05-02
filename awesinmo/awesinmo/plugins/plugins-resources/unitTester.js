/*
	@brief run and report unit tests
	@param tests array of unit test functions to execute. Each function should return 
	a boolean indicating whether test was successful or not.
	@param reportSelector optional CSS selector for result report. default is 'body'.
*/
AppGiniPlugin.unitTester = function(tests, reportSelector, stopOnError) {
	var results = []; // { title, passed, result }
	var title, result, numTests = 0, numPassed = 0, numFailed = 0;
	if(stopOnError !== true) stopOnError = false;

	// override alert and confirm boxes
	window.alert = function(text) {
		console.log('alert called with arguments: ' + text);
		return true;
	};

	window.confirm = function(text) {
		console.log('confirm called with arguments: ' + text);
		return true;
	};

	/* run each test */
	for(var t in tests) {
		if(!tests.hasOwnProperty(t)) continue;
		if(typeof(tests[t]) != 'function') continue;

		numTests++;
		result = tests[t]();
		if(result === true) {
			numPassed++; 
			console.groupCollapsed(('0000' + numTests).slice(-4) + ': ' + t);
			console.info('Passed');
		} else {
			numFailed++;
			console.group(('0000' + numTests).slice(-4) + ': ' + t);
			console.warn('Failed');
		}
		console.groupEnd();

		results.push({
			title: t.replace(/_/g, ' '),
			passed: (result === true),
			result: result
		});

		if(result !== true && stopOnError) break;
	}

	/* tests summary */
	if(undefined === reportSelector) reportSelector = 'body';

	var summary = '' +
		'<div class="tests-summary ' + (numFailed > 0 ? 'failed' : 'passed') + '">' +
			'<div class="total">' + 
				'<span class="num-tests">' + numTests + '</span> total tests' +
			'</div>' +
			'<div class="passed">' + 
				'<span class="num-passed">' + numPassed + '</span> passed tests' +
			'</div>' +
			'<div class="failed">' + 
				'<span class="num-failed">' + numFailed + '</span> failed tests' +
			'</div>' +
		'</div>';
	$j(summary).appendTo(reportSelector)

	/* display results report, assuming jQuery is loaded with $j */
	for(var i = 0; i < results.length; i++) {
		$j(
			'<div class="test-result ' + (results[i].passed ? 'passed' : 'failed') + '">' +
				(results[i].passed ? '&#9745; ' : '&#9746; ') +
				results[i].title +
				(results[i].passed ? 
					'' : 
					'<div class="details">' +
						'<div>Returned type: ' + typeof(results[i].result) + '</div>' +
						'<div>Returned value: ' + String(results[i].result) + '</div>' + 
					'</div>'
				) +
			'</div>'
		).appendTo(reportSelector);
	}

	if(stopOnError && result !== true)
		$j('<div class="test-result failed">Unit tests configured to stop on first error. Remaining tests aborted.</div>').appendTo(reportSelector);

	$j(summary).appendTo(reportSelector);

	/* report styling */
	$j(
		'<style>' +
			'.tests-summary div {' +
			'	display: inline-block;' +
			'	width: 33%;' +
			'	text-align: center;' +
			'}' +
			'.tests-summary.passed, .test-result.passed {' +
			'	background-color: #e2ffd1;' +
			'	color: #088400;' +
			'}' +
			'.tests-summary {' +
			'	padding: 1rem;' +
			'	font-size: 1.1rem;' +
			'	font-weight: bold;' +
			'	font-family: sans-serif;' +
			'}' +
			'.tests-summary.failed, .test-result.failed {' +
			'	background-color: #ffd1d1;' +
			'	color: #840000;' +
			'}' +
			'.test-result .details {' +
			'	margin-left: 3rem;' +
			'	font-size: 0.8rem;' +
			'}' +
			'.test-result.failed {' +
			'	font-weight: bold;' +
			'}' +
			'.test-result {' +
			'	padding: 0.2rem 1rem;' +
			'	margin: 0.2rem 0;' +
			'	font-family: sans-serif;' +
			'}' +
		'</style>'
	).appendTo(reportSelector);
}


