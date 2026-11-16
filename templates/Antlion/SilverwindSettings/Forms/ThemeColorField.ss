<div class="sw-colorfield">
    <input type="color" class="sw-colorfield__swatch" value="$SwatchValue" aria-label="<%t Antlion\\SilverwindSettings\\Forms\\ThemeColorField.Picker '{title} colour picker' title=$Title.ATT %>"<% if $isDisabled || $isReadonly %> disabled<% end_if %>>
    <input $AttributesHTML />
    <% if not $isReadonly %>
        <button type="button" class="sw-colorfield__reset btn btn-outline-secondary btn-sm"<% if $ThemeDefault %> data-default="$ThemeDefault"<% end_if %>>
            <%t Antlion\\SilverwindSettings\\Forms\\ThemeColorField.UseDefault 'Use default' %>
        </button>
    <% end_if %>
</div>
