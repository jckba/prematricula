import { escape, showError, flash, icon } from './ui';
let sequence = 0;
export function fieldsHtml(fields, initial = {}, prefix = 'field') {
    return fields.map((field, index) => {
        // { section, description }: subtítulo que agrupa los campos siguientes (no envía datos).
        if (field.section) return '<div class="form-section field-wide"><h3>' + escape(field.section) + '</h3>' + (field.description ? '<p>' + escape(field.description) + '</p>' : '') + '</div>';
        const id = prefix + '-' + index;
        const value = field.name.split('.').reduce((object, key) => object?.[key], initial) ?? field.value ?? '';
        const attributes = ' id="' + id + '" name="' + escape(field.name) + '" aria-describedby="' + id + '-help ' + id + '-error"' +
            (field.required ? ' required' : '') + (field.disabled ? ' disabled' : '') +
            ['min','max','step','minlength','maxlength','pattern','placeholder','autocomplete','inputmode'].map(key => field[key] !== undefined ? ' ' + key + '="' + escape(field[key]) + '"' : '').join('');
        let control;
        if (field.options) control = '<select' + attributes + '>' + field.options.map(option => '<option value="' + escape(option[0]) + '" ' + (String(option[0]) === String(value) ? 'selected' : '') + '>' + escape(option[1]) + '</option>').join('') + '</select>';
        else if (field.type === 'textarea') control = '<textarea' + attributes + ' rows="3">' + escape(value) + '</textarea>';
        else control = '<input' + attributes + ' type="' + escape(field.type || 'text') + '" value="' + escape(value) + '" ' + (field.type === 'password' ? 'autocomplete="new-password"' : '') + '>';
        return '<div class="field' + (field.wide ? ' field-wide' : '') + '"><label for="' + id + '">' + escape(field.label) + (field.required ? ' <span class="field-required" aria-hidden="true">*</span>' : '') + '</label>' + control + '<small id="' + id + '-help">' + escape(field.help || '') + '</small><p class="field-error" id="' + id + '-error" data-error="' + escape(field.name) + '"></p></div>';
    }).join('');
}
export function formDataObject(form, fields) {
    const values = new FormData(form); const result = {};
    for (const field of fields) {
        if (field.disabled || !field.name) continue;
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
/**
 * options (opcional): { description, icon, note (HTML confiable), submitLabel, successMessage, className }.
 * Con description se usa el diseño "form-modal": encabezado con ícono y subtítulo, cierre con X,
 * nota informativa y pie de acciones separado. Sin options, el formulario se ve como siempre.
 */
export function openForm(title, fields, initial = {}, onSubmit, options = {}) {
    const styled = Boolean(options.description);
    const dialog = document.createElement('dialog'); dialog.className = 'modal ' + (styled ? 'form-modal' : 'modal-wide') + (options.className ? ' ' + options.className : '');
    const titleId = 'form-title-' + (++sequence);
    dialog.setAttribute('aria-labelledby', titleId);
    const header = styled
        ? '<div class="form-modal-header">' + (options.icon ? '<span class="form-modal-icon" aria-hidden="true">' + icon(options.icon) + '</span>' : '') +
            '<div class="form-modal-heading"><h2 id="' + titleId + '">' + escape(title) + '</h2><p>' + escape(options.description) + '</p></div>' +
            '<button type="button" class="form-modal-close" data-cancel aria-label="Cerrar">×</button></div>' +
            '<p class="form-modal-legend">Los campos marcados con <span class="field-required" aria-hidden="true">*</span> son obligatorios.</p>'
        : '<div class="modal-header"><h2 id="' + titleId + '">' + escape(title) + '</h2><button type="button" class="btn btn-secondary" data-cancel>Cerrar</button></div><p class="muted">Los campos con * son obligatorios. Los cambios se validan en el servidor.</p>';
    dialog.innerHTML = '<form>' + header + '<div class="form-grid">' + fieldsHtml(fields, initial, 'form-' + sequence) + '</div>' + (options.note || '') +
        '<div class="notice notice-danger" data-form-error role="alert" hidden></div>' +
        '<div class="toolbar' + (styled ? ' form-modal-actions' : '') + '"><button type="button" class="btn btn-secondary" data-cancel>Cancelar</button><button type="submit" class="btn' + (styled ? ' btn-raised' : '') + '">' + escape(options.submitLabel || 'Guardar cambios') + '</button></div></form>';
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
            dialog.close(); flash(options.successMessage || 'Los cambios se guardaron correctamente.');
        } catch (error) {
            errorBox.textContent = error.message + ' ' + Object.values(error.errors || {}).flat().join(' ');
            errorBox.hidden = false; showError(error, dialog);
        } finally {
            form.removeAttribute('aria-busy'); buttons.forEach(button => button.disabled = false);
            dialog.removeEventListener('cancel', preventClose);
        }
    };
    document.body.append(dialog); dialog.showModal();
    if (styled) dialog.querySelector('.form-grid input, .form-grid select, .form-grid textarea')?.focus();
    return dialog;
}
