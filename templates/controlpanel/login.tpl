<h1>Login to {$title}</h1>

<p>Please enter your details below.</p>

<form action='{if $regex[1] neq ''}{$regex[1]}{else}login{/if}' method='post'>
  <p><label for="cp5_uname"><b>Username</b>: </label><input type="text" name="cp5_uname" id="cp5_uname" value="" /></p>
  <p><label for="cp5_pword"><b>Password</b>: </label><input type="password" name="cp5_pword" id="cp5_pword" value="" /></p>
  <p><label>&nbsp;</label><input type="submit" name="login" value="Login" /></p>
  <input type='hidden' name='cp5' value='session.login' />
</form>

<p>If you are having problems please contact the admin: <b>{$admin}</b>.</p>

{*<pre>{php}print_r($this);{/php}</pre>*}
