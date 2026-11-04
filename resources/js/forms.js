import { escape, showError, flash } from './ui';
let sequence = 0;
export function fieldsHtml(fields, initial = {}, prefix = 'field') {
    return fields.map((field, index) => {
        const id = prefix + '-' + index;
        const value = field.name.split('.').reduce((object, key) => object?.[key], initial) ?? field.value ?? '';
        const attributes = ' id="' + id + '" name="' + escape(field.name) + '" aria-describedby="' + id + '-help ' + id + '-error"' +
            (field.required ? ' required' : '') + (field.disabled ? ' disabled' : '') +
            ['min','max','step','minlength','maxlength','pattern'].map(key => field[key] !== undefined ? ' ' + key + '="' + escape(field[key]) + '"' : '').join('');
        let control;
        if (field.options) control = '<select' + attributes + '>' + field.options.map(option => '<option value="' + escape(option[0]) + '" ' + (String(option[0]) === String(value) ? 'selected' : '') + '>' + escape(option[1]) + '</option>').join('') + '</select>';
        else if (field.type === 'textarea') control = '<textarea' + attributes + ' rows="3">' + escape(value) + '</textarea>';
        else control = '<input' + attributes + ' type="' + escape(field.type || 'text') + '" value="' + escape(value) + '" ' + (field.type === 'password' ? 'autocomplete="new-password"' : '') + '>';
        return '<div class="field"><label for="' + id + '">' + escape(field.label) + (field.required ? ' <span aria-hidden="true">*</span>' : '') + '</label>' + control + '<small id="' + id + '-help">' + escape(field.help || '') + '</small><p class="field-error" id="' + id + '-error" data-error="' + escape(field.name) + '"></p></div>';
    }).join('');
}
export function formDataObject(form, fields) {
    const values = new FormData(form); const result = {};
    for (const field of fields) {
        if (field.disabled) continue;
        let value = values.get(field.name);
        if (value === '' && !field.required && field.type === 'password') continue;
        if (value === '' && field.omitEmpty) continue;
        if (field.nullable && value === '') value = null;
        if (field.type === 'number' && value !== '' && value !== null) value = Number(value);
        const path = field.name.split('.'); let target = result;
        path.forEach((key, index) => {
            if (index === path.length - 1) target[key] = value;
            else { target[key] ??= {}; target = target[key]; }
        });
    }
    return result;
}
export function openForm(title, fields, initial = {}, onSubmit) {
    const dialog = document.createElement('dialog'); dialog.className = 'modal modal-wide';
    dialog.setAttribute('aria-label', title);
    dialog.innerHTML = '<form><div class="modal-header"><h2>' + escape(title) + '</h2><button type="button" class="btn btn-secondary" data-cancel>Cerrar</button></div><p class="muted">Los campos con * son obligatorios. Los cambios se validan en el servidor.</p><div class="form-grid">' + fieldsHtml(fields, initial, 'form-' + (++sequence)) + '</div><div class="notice notice-danger" data-form-error role="alert" hidden></div><div class="toolbar"><button type="button" class="btn btn-secondary" data-cancel>Cancelar</button><button type="submit" class="btn">Guardar cambios</button></div></form>';
    dialog.querySelectorAll('[data-cancel]').forEach(button => button.onclick = () => dialog.close());
    dialog.addEventListener('close', () => dialog.remove(), { once: true });
    const form = dialog.querySelector('form');
    form.onsubmit = async event => {
        event.preventDefault(); if (!form.reportValidity()) return;
        const buttons = [...dialog.querySelectorAll('button')];
        if (form.getAttribute('aria-busy') === 'true') return;
        form.setAttribute('aria-busy', 'true'); buttons.forEach(button => button.disabled = true);
        dialog.querySelectorAll('[data-error]').forEach(el => el.textContent = '');
        dialog.querySelectorAll('[aria-invalid]').forEach(el => el.removeAttribute('aria-invalid'));
        const errorBox = dialog.querySelector('[data-form-error]'); errorBox.hidden = true;
        const preventClose = event => event.preventDefault(); dialog.addEventListener('cancel', preventClose);
        try {
            await onSubmit(formDataObject(form, fields), dialog);
            dialog.close(); flash('Los cambios se guardaron correctamente.');
        } catch (error) {
            errorBox.textContent = error.message + ' ' + Object.values(error.errors || {}).flat().join(' ');
            errorBox.hidden = false; showError(error, dialog);
        } finally {
            form.removeAttribute('aria-busy'); buttons.forEach(button => button.disabled = false);
            dialog.removeEventListener('cancel', preventClose);
        }
    };
    document.body.append(dialog); dialog.showModal(); return dialog;
}
