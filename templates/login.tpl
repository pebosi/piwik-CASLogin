{include file="Login/templates/header.tpl"}

<div id="login">

{if $AccessErrorString}
<div id="login_error"><strong>{'General_Error'|translate}</strong>: {$AccessErrorString}<br /></div>
{/if}

<div id="loginbox">
    <div id="loginlink">
		<a href="index.php?module=CASLogin&amp;action=redirectToCAS">{'Login_LogIn'|translate}</a>
    </div>
</div>

</div>

</body>
</html>
