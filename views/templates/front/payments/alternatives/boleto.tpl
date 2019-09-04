<form name="{$module}" id="{$module}-{$key}-form" action="{$link->getModuleLink($module, 'payment', [], true)|escape:'html'}" method="POST">
    <input id="{$module}-{$key}-source" type="hidden" name="source" value="{$key}" required>
    <ul class="form-list" >
        <li>
            <label for="name" class="required">{l s='Name' mod='checkoutcom'}</label>
            <input type="text" class="form-control input-text cvv required-entry validate-cc-cvn" id="name" name="name" value="" />
        </li>
        <li>
            <label for="cpf" class="required">{l s='Cadastro de Pessoas Físicas' mod='checkoutcom'}</label>
            <input type="text" class="form-control input-text cvv required-entry validate-cc-cvn" id="cpf" name="cpf" value="" />
        </li>
        <li>
            <label for="birthDate" class="required">{l s='Birthdate' mod='checkoutcom'}</label>
            <input type="date" class="form-control input-text cvv required-entry validate-cc-cvn" id="birthDate" name="birthDate" value="" />
        </li>
    </ul>
</form>