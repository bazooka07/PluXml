function dateNow(field,delta) {
	var d = new Date();
	// convert to msec, add local time zone offset
	// get UTC time in msec
	var utc = d.getTime() + (d.getTimezoneOffset() * 60000);
	// create new Date object for different city using supplied offset
	var now = new Date(utc + (1000*delta));
	var y = now.getFullYear();
	var m = now.getMonth();
	var d = now.getDate();
	var h = now.getHours();
	var i = now.getMinutes();
	if(i <= 9){i = '0'+i;}
	if(h <= 9){h = '0'+h;}
	if(d <= 9){d = '0'+d;}
	m = m+1;
	if(m <= 9){m = '0'+m;}
	document.getElementsByName(field+'_day')['0'].value = d;
	document.getElementsByName(field+'_time')['0'].value = h+":"+i;
	document.getElementsByName(field+'_month')['0'].value = m;
	document.getElementsByName(field+'_year')['0'].value = y;
}
function answerCom(where,id,author) {
	document.getElementById('id_parent').value=id;
	//addText(where, '<a href="#c'+id+'">@'+author+'</a> :\n');
	scrollTo(0,0);
}
function addText(where, open, close) {
	close = close==undefined ? '' : close;
	var formfield = document.getElementsByName(where)['0'];
	// IE support
	if (document.selection && document.selection.createRange) {
		formfield.focus();
		sel = document.selection.createRange();
		sel.text = open + sel.text + close;
		formfield.focus();
	}
	// Moz support
	else if (formfield.selectionStart || formfield.selectionStart == '0') {
		var startPos = formfield.selectionStart;
		var endPos = formfield.selectionEnd;
		var restoreTop = formfield.scrollTop;
		formfield.value = formfield.value.substring(0, startPos) + open + formfield.value.substring(startPos, endPos) + close + formfield.value.substring(endPos, formfield.value.length);
		formfield.selectionStart = formfield.selectionEnd = endPos + open.length + close.length;
		if (restoreTop > 0) formfield.scrollTop = restoreTop;
		formfield.focus();
	}
	// Fallback support for other browsers
	else {
		formfield.value += open + close;
		formfield.focus();
	}
	return;
}
function checkAll(form1, fieldname) {
	const elts = form1.elements[fieldname]; // tableau
	for(var i=0, iMax=elts.length; i<iMax; i++) {
		if(elts[i].value.trim().length > 0) {
			elts[i].checked = true;
		}
	}
	return false;
}
function confirmAction(form1, idField, selvalue, fieldname, msg) {
	if(document.getElementById(idField).value != selvalue) { return false; }

	const elts = form1.elements[fieldname]; // tableau
	var count = 0;
	for(var i=0, iMax=elts.length; i<iMax; i++) {
		if(elts[i].type == 'checkbox' && elts[i].checked) {
			count++;
		}
	}
	return (count > 0) ? confirm(msg.replace(/%d/, count)) : false;
}

function toggleDiv(divId,togglerId,on,off){
	var toggler = document.getElementById(togglerId);
	if(document.getElementById(divId).style.display == 'none') {
		document.getElementById(divId).style.display = 'block';
		toggler.innerHTML=off;
	} else {
		document.getElementById(divId).style.display = 'none';
		toggler.innerHTML=on;
	}
}
function insTag(where, tag) {
	var formfield = document.getElementsByName(where)['0'];
	var tags = formfield.value.split(', ');
	if (tags.indexOf(tag) != -1) return;
	if(formfield.value=='')
		formfield.value=tag;
	else
		formfield.value = formfield.value+', '+tag;
}
