'use strict';

const roleSelect = document.getElementById('role');
const organizationField = document.getElementById('organization-field');
const organizationInput = document.getElementById('organization_name');

function updateOrganizationField() {
    const isOrganization = roleSelect.value === 'organization';
    organizationField.hidden = !isOrganization;
    organizationInput.required = isOrganization;
    organizationInput.disabled = !isOrganization;
}

if (roleSelect && organizationField && organizationInput) {
    roleSelect.addEventListener('change', updateOrganizationField);
    updateOrganizationField();
}

const errorSummary = document.getElementById('form-errors');
if (errorSummary) {
    errorSummary.focus();
}
