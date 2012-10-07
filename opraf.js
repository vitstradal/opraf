function place_comments_one_div(img_id, comments)
{
	var img = document.getElementById(img_id);
	if( img == null ) { 
		return;
	}
	var par = img.parentNode;
	var w = img.clientWidth;
	var h = img.clientHeight;
	var w_skip = 10;
	var h_skip = 5;
	var pointer_min_h = 30;

	var bott_max = 0;
	var comments_sorted = comments.sort(function (a,b) {
		return a[2] - b[2];
		//pokus o hezci kladeni poiteru, ale nic moc
		if( a[3] < b[3] ) { 
			return (a[2] + pointer_min_h)- b[2];
		} else {
			return (a[2] - pointer_min_h)- b[2];
		}

	});
	//console.log("w:" + w);
	for (c in comments_sorted) {
		var id = comments_sorted[c][0];
		var x  = comments_sorted[c][1];
		var y  = comments_sorted[c][2];

		var el = document.getElementById(id);
		var elp = document.getElementById(id + "-pointer");

		if( el == null  || elp == null ) { 
			continue;
		}

		par.appendChild(elp);
		par.appendChild(el);

		var delta_y = (y > bott_max) ?  0: bott_max - y + h_skip;

		elp.style.left = x;
		elp.style.top = y ;
		elp.style.width = w - x  + w_skip;
		elp.style.height = pointer_min_h + delta_y;
		elp.img_id = img_id;
		el.img_id = img_id;

		el.style.position = 'absolute';
		el.style.left = w + w_skip;
		el.style.top = y + delta_y;

		var bott =  el.offsetTop + el.offsetHeight;
		bott_max = ( bott_max > bott ) ?  bott_max : bott;

		//console.log( "par.w:"  + par.style.width);

	}
	if( par.offsetHeight < bott_max ) {
		//par.style.height = bott_max;
		//alert("preteklo to:"+ par.offsetHeight +",mx:" + bott_max );
		par.style.height = bott_max;

	}
}

// ctrl-enter submits form
function textarea_onkey(ev)
{
	//console.log("ev:" + ev.keyCode + "," + ev.ctrlKey);
	if( (ev.keyCode  == 13 || ev.keyCode == 10 )  && ev.ctrlKey ) {
		var form = document.getElementById('commform');
		if( form ) { 
			save_scroll(form);
			//form.action ='';
			form.submit();
		}
		return true;
	}
	return false;
}

//hide comment  form
function close_commform() {

	var formdiv = document.getElementById('commform-div');
	if( formdiv == null ) {
		alert("form null");
		return true;
	}
	formdiv.style.display = 'none';
	return false;
}

// show comment form, when clicked to image
function img_click(element, ev) {

	var dx, dy;
	var par = element.parentNode;
	if( ev.pageX != null ) { 
		dx = ev.pageX - par.offsetLeft;
		dy = ev.pageY - par.offsetTop;
	} else { //IE
		dx = ev.offsetX;
		dy = ev.offsetY;
	}
	var img_id = element.id;
	if( element.img_id != null ) {
		// click was to '-pointer'
		img_id = element.img_id;
	}
	return show_form(img_id, dx, dy, '', '', '', '');
}

// show comment form, when 'edit' button pressed
function box_edit(button) 
{
	var divbox = button.parentNode.parentNode.parentNode;
	var id = divbox.id;
	//alert("id: " +  id);
	var divpointer = document.getElementById(divbox.id + '-pointer');
	var text_el = document.getElementById(divbox.id + '-text');
	var text = text_el.innerHTML.unescapeHTML();

	var dx = parseInt(divpointer.style.left);
	var dy = parseInt(divpointer.style.top);
	//alert('not yet 2:' + text + text_el); // + divpointer.style.top "x" + divpo );
	id = id.substring(2);
	return show_form(divbox.img_id, dx, dy, id, text, 'update');

}

//fill up comment form and show him
function show_form(img_id, dx, dy, id, text, action) {
	var form = document.getElementById('commform');
	var formdiv = document.getElementById('commform-div');
	var textarea = document.getElementById('commform-text');
	var inputX  = document.getElementById('commform-x');
	var inputY  = document.getElementById('commform-y');
	var inputImgId  = document.getElementById('commform-img-id');
	var inputId  = document.getElementById('commform-id');
	var inputAction  = document.getElementById('commform-action');
	var img = document.getElementById(img_id);

	if( formdiv == null || textarea == null ) {
		alert("form null");
		return 1;
	}

	//form.action = "#" +  img_id;

	// set hidden values
	inputX.value = dx;
	inputY.value = dy;
	inputImgId.value = img_id;
	inputId.value = id;
	inputAction.value = action;
	textarea.value = text;

	//textarea.value = "dxy:"+ dx + "x" + dy + "\n" + 'id:' + img_id;

	// show form
	formdiv.style.display = 'block';
	formdiv.style.left = dx;
	formdiv.style.top = dy;
	
	img.parentNode.appendChild(formdiv);

	textarea.focus();

	return true;

}

function box_onmouseover(box, done)
{
	var id = box.id;
	var pointer = document.getElementById(box.id + '-pointer');
	pointer.className = done ? 'pointer-done-hi' : 'pointer-hi';
	//console.log('mouseout');
		
}

function box_onmouseout(box, done)
{
	var id = box.id;
	var pointer = document.getElementById(box.id + '-pointer');
	pointer.className = done ? 'pointer-done' : 'pointer';

	//console.log('mousein');
}

function save_scroll(form)
{
	//alert('save_scroll:' + document.body.scrollTop);
	form.scroll.value =  document.body.scrollTop;
	//alert('save_scroll:' + form.scroll.value);
	

	return true;
}


String.prototype.unescapeHTML = function () {                                       
        return(                                                                 
            this.replace(/&amp;/g,'&').                                         
                replace(/&gt;/g,'>').                                           
                replace(/&lt;/g,'<').                                           
                replace(/&quot;/g,'"')                                         
        );                                                                     
};

