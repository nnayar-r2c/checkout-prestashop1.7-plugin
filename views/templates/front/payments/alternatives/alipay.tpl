<form name="{$module}" id="{$module}-{$key}-form" action="{$link->getModuleLink($module, 'payment', [], true)|escape:'html'}" method="POST">
    <input id="{$module}-{$key}-source" type="hidden" name="source" value="{$key}" required>
</form>