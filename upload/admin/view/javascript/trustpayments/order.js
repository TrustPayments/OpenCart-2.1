function onTrustPaymentsClick () {
	var route = $(this).attr('data-route');
	$.ajax({
		dataType: "json",
		url: 'index.php?route=' + route + '&token=' + document.adminToken + '&order_id=' + document.orderId,
		success:  function( json ) {
			if (json['error']) {
				$('#content > .container-fluid').prepend('<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> ' + json['error'] + ' <button type="button" class="close" data-dismiss="alert">&times;</button></div>');
			} else	if (json['success']) {
				$('#content > .container-fluid').prepend('<div class="alert alert-success"><i class="fa fa-check-circle"></i> ' + json['success'] + ' <button type="button" class="close" data-dismiss="alert">&times;</button></div>');
				setTrustPaymentsButtons(json['buttons']);
			} else if (json['redirect']) {
				window.location = json['redirect'].replace(/&amp;/g, '&');
			} else if (json['reload']) {
				window.location.reload();
			}
		},
		error: function (jqXHR, textStatus, errorThrown) {
			$('#content > .container-fluid').prepend('<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <strong>' + textStatus + '</strong> ' + errorThrown + ' <button type="button" class="close" data-dismiss="alert">&times;</button></div>');
		}
	});
}
function setTrustPaymentsButtons(buttons) {
	$('.trustpayments-btn').remove();
	for (var i = 0; i < buttons.length; i++) {
		$('#content > .page-header > .container-fluid > .pull-right').prepend(' ');
		$('<a>').attr({
			'id': 'trustpayments-button-' + i,
			'data-toggle': 'tooltip',
			'title': buttons[i]['text'],
			'data-route': buttons[i]['route'],
			'class': 'trustpayments-btn btn btn-info',
		}).html(
			$('<i>').attr({
				'class': 'fa fa-' + buttons[i]['icon']
			})
		).prependTo('#content > .page-header > .container-fluid > .pull-right'
		).on('click', onTrustPaymentsClick);
	}
}
function addTrustPaymentsError(message) {
	$('#content > .container-fluid').prepend('<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> ' + message + ' <button type="button" class="close" data-dismiss="alert">&times;</button></div>');
}