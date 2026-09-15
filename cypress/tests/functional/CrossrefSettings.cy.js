/**
 * @file cypress/tests/functional/CrossrefSettings.cy.js
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Functional tests: Crossref offered as the DOI registration agency of the
 * press, with its settings fully translated.
 *
 * Parameters (--env): contextPath, adminUser, adminPassword (a press manager).
 * Captcha on login must be off for the run. The plugin must be enabled.
 * Nothing is saved: the agency is only chosen on the form.
 */

describe('Crossref plugin for OMP', function() {
	const contextPath = Cypress.env('contextPath') || 'publicknowledge';
	const adminUser = Cypress.env('adminUser') || 'admin';
	const adminPassword = Cypress.env('adminPassword') || 'admin';

	// Signs in through requests: the login page can re-render while it is typed into.
	const login = () => {
		cy.clearCookies();
		cy.request('/index.php/' + contextPath + '/login').then((response) => {
			const token = /name="csrfToken" value="([^"]+)"/.exec(response.body)[1];
			// OMP 3.5 redirects to a URL with the language, which would turn the POST into a GET.
			const action = /<form[^>]*id="login"[^>]*action="([^"]+)"/.exec(response.body)[1];
			cy.request({method: 'POST', url: action, form: true, body: {csrfToken: token, username: adminUser, password: adminPassword}, log: false});
		});
	};

	it('Offers Crossref as the registration agency, with no untranslated text', function() {
		login();
		cy.visit('/index.php/' + contextPath + '/management/settings/distribution?reload=' + Date.now() + '#dois/doisRegistration');
		cy.get('select[name="registrationAgency"]', {timeout: 60000}).should('be.visible')
			.find('option[value="crossrefplugin"]').should('have.length', 1);
		cy.get('select[name="registrationAgency"]').select('crossrefplugin');
		['depositorName', 'depositorEmail', 'username', 'password'].forEach((name) => {
			cy.get('input[name="' + name + '"]').should('be.visible');
		});
		cy.get('input[name="password"]').should('have.attr', 'type', 'password');
		cy.get('select[name="registrationAgency"]').closest('form').invoke('text').then((text) => {
			expect(text).not.to.contain('##');
		});
	});
});
