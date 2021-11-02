<script language="JavaScript" type="text/javascript">
//
// toggleRowColor.js
//
// Java Script for toggling background color of selected row
//
// CopyRight (c) 2007 RainbowFish Software
//
<!--        

function addLoadEvent(func) {
    var oldonload = window.onload;
    if (typeof window.onload != 'function') {
        window.onload = func;
    } else {
        window.onload = function() {
            oldonload();
            func();
        }
    }
}

function addClass(element,value) {
    if (!element.className) {
        element.className = value;
    } else {
        newClassName = element.className;
        newClassName+= value;
        element.className = newClassName;
    }
}

function highlightRows() {
  if(!document.getElementsByTagName) return false;
  	var tables = document.getElementsByTagName("table");
	for (var m=0; m<tables.length; m++) {
		if (tables[m].className.indexOf("mouseover") != -1) {
			 var rows = tables[m].getElementsByTagName("tr");
			 for (var i=0; i<rows.length; i++) {
                   // skip table headers
                   if (rows[i].className.toUpperCase() == "LISTHEAD")
                       continue;
				   rows[i].oldClassName = rows[i].className
				   rows[i].onmouseover = function() {
					  if( this.className.indexOf("selected") == -1)
						 addClass(this,"highlight");
				   }
				   rows[i].onmouseout = function() {
					  if( this.className.indexOf("selected") == -1)
						 this.className = this.oldClassName
				   }
			 }
		}
	}
}

function selectRowCheckbox(row) {
	var checkbox = row.getElementsByTagName("input")[0];
	if (checkbox.checked == true) {
		checkbox.checked = false;
	} else
	if (checkbox.checked == false) {
		checkbox.checked = true;
	}
}

function radioRowOnClick() {
    var table = this.parentNode.parentNode;
    var trs = table.getElementsByTagName("tr");
    for (var j=0; j<trs.length; j++) {
        if (trs[j].className.indexOf("selected") != -1) {
            trs[j].className = trs[j].oldClassName;
        } else if (this == trs[j]) {
            addClass(trs[j],"selected");
        }
    }
    selectRowCheckbox(this);
}

function optionRowOnClick() {
    if (this.className.indexOf("selected") != -1) {
		this.className = this.oldClassName;
	} else {
		addClass(this,"selected");
	}
	selectRowCheckbox(this);
}

function lockRow() {
  	var tables = document.getElementsByTagName("table");
	for (var m=0; m<tables.length; m++) {
        var func = "";
        if (tables[m].className.indexOf("radiorow") != -1) {
            func = radioRowOnClick;
		} else if (tables[m].className.indexOf("optionrow") != -1) {
            func = optionRowOnClick;
		}
		var rows = tables[m].getElementsByTagName("tr");
		for (var i=0; i<rows.length; i++) {
			rows[i].oldClassName = rows[i].className;
			rows[i].onclick = func;
		}
	}
}

addLoadEvent(lockRow);
addLoadEvent(highlightRows);

function radioOnClick(evt) {
    var table = this.parentNode.parentNode;
    var trs = table.getElementsByTagName("tr");
    for (var j=0; j<trs.length; j++) {
        if (trs[j].className.indexOf("selected") != -1){
            trs[j].className = trs[j].oldClassName;
        } else if (this.parentNode.parentNode == trs[j]) {
            addClass(trs[j],"selected");
        }
    }
    selectRowCheckbox(this.parentNode.parentNode);
}

function checkboxOnClick(evt) {
    var tr = this.parentNode.parentNode;
    if (tr.className.indexOf("selected") != -1){
		tr.className = tr.oldClassName;
	} else {
		addClass(tr,"selected");
	}
    if (window.event && !window.event.cancelBubble) {
		window.event.cancelBubble = "true";
	} else {
		evt.stopPropagation();
	}
}

function lockRowUsingCheckbox() {
	var tables = document.getElementsByTagName("table");
	for (var m=0; m<tables.length; m++) {
        var func = "";
        if (tables[m].className.indexOf("radiorow") != -1) {
            func = radioOnClick;
		} else if (tables[m].className.indexOf("optionrow") != -1) {
            func = checkboxOnClick;
		}
		var checkboxes = tables[m].getElementsByTagName("input");
		for (var i=0; i<checkboxes.length; i++) {
            if ((checkboxes[i].type == 'checkbox') ||
                (checkboxes[i].type == 'radio')) {
			    checkboxes[i].onclick = func;
            }
		}
	}
}
addLoadEvent(lockRowUsingCheckbox);

//-->
</script>
