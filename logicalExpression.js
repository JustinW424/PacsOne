<script language="JavaScript" type="text/javascript">
<!--        
//
// logicalExpression.js
//
// JavaScript for advanced logical expression in automatic Dicom image routing
//
// CopyRight (c) 2003-2012 RainbowFish Software
//
function onLeftBracket(form)
{
    form.logicalExpr.value += "(";
    return true;
}

function onRightBracket(form)
{
    form.logicalExpr.value += ")";
    return true;
}

function onAndButton(form)
{
    form.logicalExpr.value += " AND ";
    return true;
}

function onOrButton(form)
{
    form.logicalExpr.value += " OR ";
    return true;
}

function onAppendButton(form)
{
    var select = form.logicalTag;
    var str = select.options[select.selectedIndex].text;
    var index = str.lastIndexOf("(");
    var group = str.substr(index+1, 4);
    index = str.lastIndexOf(",");
    var element = str.substr(index+1, 4);
    var tag = group + element;
    var expr = form.tokenExpr.value;
    form.logicalExpr.value += "%" + tag + "=" + expr + "%";
    return true;
}

function onResetButton(form)
{
    form.logicalExpr.value = "";
    return true;
}

//-->
</script>
