/* Author:

*/

var SQM = {
	push: function(query, qid) {
		/**	if ($F('sql').length > 1024) {
			$('output').update('<p class="error">Query too long (1024 character limit).</p>');
			return;
			}**/

		$('user_sql').request({
			onComplete: function(response){
				$(qid+'-response').update(response.responseText);
			}
		});

		var ppQuery = prettyPrintOne(query.escapeHTML(), 'sql', false);

		var li = new Element('li');
		li.id = qid;
		li.update('<pre id="' + qid + '-query" class="prettyprint lang-sql"><code>'
			+ ppQuery + '</code></pre><div id="' + qid +'-response" class="response"></div>');
			// style="display:none"
			//<a href="javascript:SQM.delete_history();">X</a>

		//TODO slide down new inertion
		$('history').insert({top:li});

		// TODO: pop if a size limit is reached? Mask?
	},

	delete_history: function () {
		if (!confirm("really?")) {return;}
		alert(this);
	}
};

document.observe("dom:loaded", function() {
	$('user_sql').observe('submit', function(evt) {
		evt.stop();

		if (!$('results').visible()) {
			Effect.SlideDown('results', { duration: 0.5});
		}

		$('sql').value = $F('sql').strip();

		//push sql statement to history and execute
		SQM.push($F('sql'), 'output-' +new Date().getTime());
	});

	$('user_create_db').show();
	$('user_create_db').observe('submit', function(evt) {
		evt.stop();

		// Show throbber.
		$('create').hide();
		$('throbber_create').show();

		//TODO: Should check to see if db is valid, first
		$('user_create_db').request({
			onSuccess: function(response){
				$('entry').remove();
				Effect.SlideDown('user_sql', { duration: 0.5});
			},
			onFailure: function(response){
				$('user_create_db').replace( response.responseText
					|| '<p class="error">An unknown error occurred.</p>');
			}
		});
	});

});