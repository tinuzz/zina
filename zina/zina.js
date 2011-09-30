function WindowOpen(url, title, w, h, sbars) {
	var sc_w = 800, sc_h = 600;
	if (window.screen) {
   	sc_w = screen.width;
   	sc_h = screen.height;
	}
	if (arguments.length > 5 && arguments[5] == 'full') {
		var w = sc_w, h = sc_h;
		x=y=0;
	} else {
		var x = ((sc_w-w)/2)+0, y = ((sc_h-h)/2)-20;
	}
	window.open(url, title,'toolbar=no,status=no,scrollbars='+sbars+',resizable=no,width='+w+',height='+h+',left='+x+',top='+y+',screenX='+x+',screenY='+y);
}

function SubmitForm(form_id, url, type) {
	var form = document.forms[form_id];
	if (type == 'play' && typeof window.zinampSelect == 'function') {
		zinampSelect(form, url);
	} else {
		form.action = url;
		form.submit();
	}
}

function zinaSubmit(form_id, opt) {
	if(typeof window.zinampSelect == 'function' && opt == 'm=play') {
		var form = document.forms[form_id];
		var url = form.action += '&'+opt;
		zinampSelect(form, url);
	} else {
		document.forms[form_id].action += '&'+opt;
		document.forms[form_id].submit();
	}
}

function CheckBoxes(form_id, x){
	var e = document.forms[form_id].elements;
	for (var i=0; i < e.length; i++) {
		if (e[i].type == "checkbox") { e[i].checked = x; }
	}
}

function CheckIt(form_id, url, error, type) {
	var form = document.forms[form_id];
	var e = form.elements;

	var err = 1;
	for (var i=0; i < e.length; i++) {
		if (e[i].type == "checkbox" && e[i].checked) { err = 0; break; }
	}

	if (err == 1) {
		alert(error);
	} else {
		if(type == 'play' && typeof window.zinampSelect == 'function') {
			zinampSelectPost(form, url);
		} else {
			form.action = url;
			form.submit();
		}
	}
}

function selectCheck(formid, url, error, sess, type) {
	var form = document.forms[formid];
	var selected = form.playlist.options[form.playlist.selectedIndex].value;

	var err = 0;
	if (type == "view") {
		if (selected == "new_zina_list") err = 1;
	} else { //play/lofi
		if (selected == "new_zina_list" || (selected == "zina_session_playlist" && sess == 0)) {
			err = 1;
		}
	}
	if (err == 1) {
		alert(error);
	} else {
		if (type == 'play' && typeof window.zinampSelect == 'function') {
			zinampSelect(form, url);
		} else {
			form.action = url;
			form.submit();
		}
	}
}

function selectCheckAjax(url, form_id, id, error) {
	var form = document.forms[form_id];
	var e = form.elements;

	err = 1;
	for (var i=0; i < e.length; i++) {
		if (e[i].type == "checkbox" && e[i].checked) { err = 0; break; }
	}

	if (err == 1) {
		alert(error);
	} else {
		var selected = form.playlist.options[form.playlist.selectedIndex].value;
		if (selected == 'new_zina_list') {
			form.action = url;
			form.submit();
		} else {
			params = createQuery(form);
			ajaxPost(url, params, id);
		}
	}
}

function xmlRequest(url){req=false;if(window.XMLHttpRequest){try{req=new XMLHttpRequest();}catch(e){req=false;}}else if(window.ActiveXObject){try{req=new ActiveXObject("Msxml2.XMLHTTP");}catch(e){try{req=new ActiveXObject("Microsoft.XMLHTTP");}catch(e){req=false;}}}return req;}

function ajax(url, id){
	req = xmlRequest();
	if (req) {
		req.open("GET", url+'&hash='+Math.random(), true);
		req.onreadystatechange = function() {
			if (req.readyState==4) {
				if (req.responseText != "") {
					document.getElementById(id).innerHTML = req.responseText;
				}
			}
		}
		req.send('');
	}
}

function ajaxPost(url, params, id){
	req = xmlRequest();
	if (req) {
		req.open("POST", url, true);
		req.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
		req.setRequestHeader("Content-length", params.length);
		req.setRequestHeader("Connection", "close");

		req.onreadystatechange = function() {
			if (req.readyState==4) {
				if (req.responseText != "") {
					document.getElementById(id).innerHTML = req.responseText;
				}
			}
		}
		req.send(params);
	}
}
function createQuery(form) {
	var e = form.elements;
	var pairs = new Array();

	for (var i=0; i<e.length;i++) {
		name = e[i].name;
		value = getElementValue(e[i]);
		if (name && value) {
			pairs.push(name + '=' + encodeURIComponent(value));
		}
	}

	return pairs.join('&');
}

function getElementValue(e) {
	if (e.length != null)
		var type = e[0].type;
	if (typeof(type) == 'undefined' || type == 0)
		var type = e.type;

	switch (type) {
		case 'undefined':
			break;
		case 'radio':
			for (var i=0;i<e.length;i++)
				if (e[i].checked == true) return e[i].value;
			break;
		case 'select-multiple':
			var multi = new Array();
			for (var i=0;i<e.length;i++) {
				if (e[i].selected == true) multi[multi.length] = e[i].value;
			}
			return multi;
		case 'checkbox':
			//todo: is checked necessary?
			if (e.checked) return e.value;
			break;
		default:
			return e.value;
	}
}
/* Cookie: Copyright (c) 2006 Klaus Hartl (stilbuero.de)
 * Dual licensed under the MIT and GPL licenses:
 * http://www.opensource.org/licenses/mit-license.php
 * http://www.gnu.org/licenses/gpl.html
 */

function zina_cookie(name,value,options){if(typeof value!='undefined'){options=options||{};if(value===null){value='';options.expires=-1;}var expires='';if(options.expires&&(typeof options.expires=='number'||options.expires.toUTCString)){var date;if(typeof options.expires=='number'){date=new Date();date.setTime(date.getTime()+(options.expires*24*60*60*1000));}else{date=options.expires;}expires='; expires='+date.toUTCString();}var path=options.path?'; path='+(options.path):'';var domain=options.domain?'; domain='+(options.domain):'';var secure=options.secure?'; secure':'';document.cookie=[name,'=',encodeURIComponent(value),expires,path,domain,secure].join('');}else{var cookieValue=null;if(document.cookie&&document.cookie!=''){var cookies=document.cookie.split(';');for(var i=0;i<cookies.length;i++){var cookie=jQuery.trim(cookies[i]);if(cookie.substring(0,name.length+1)==(name+'=')){cookieValue=decodeURIComponent(cookie.substring(name.length+1));break;}}}return cookieValue;}}

var zPlay=0;var zi=0;
function nextImage(){zi++;if(zi>zImages.length-1)zi=0;setImage(zi);}
function prevImage(){zi--;if(zi<0)zi=zImages.length-1;setImage(zi);}
function setImage(idx){
	document.getElementById('zImage').src=zImages[idx];
	if (document.getElementById('zImageText') != null) {
		var txt = document.getElementById('zImageText');
		txt.innerHTML=zImagesText[idx];
		/* IE bug */
		if (zImagesText[idx] == '')
			txt.style.display="inline";
		else
			txt.style.display="block";
	}
}
function startSlideShow(msec){nextImage();zPlay=setInterval('nextImage()',msec);}
function stopSlideShow(){clearTimeout(zPlay);}
function viewImage(){location=zImagesURL[zi];}
