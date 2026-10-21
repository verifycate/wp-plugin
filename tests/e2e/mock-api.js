#!/usr/bin/env node
/**
 * Minimal Verifycate API double for local end-to-end tests.
 *
 * It implements the endpoints the WordPress plugin uses, keeps state in
 * memory, and logs every request. Point the plugin at
 * http://host.docker.internal:4567 with workspace "acme" and API key
 * "vc_test_key".
 */

'use strict';

const http = require('http');
const { randomUUID } = require('crypto');

const PORT = Number(process.env.PORT || 4567);
const API_KEY = process.env.API_KEY || 'vc_test_key';
const WORKSPACE = 'acme';

const state = {
	certificates: new Map(),
	issues: new Map(),
	requests: [],
};

function now() {
	return new Date().toISOString();
}

function seed() {
	const created = '2026-01-15T09:30:00Z';
	state.certificates.set('cert-1', {
		id: 'cert-1',
		name: 'Leadership Essentials',
		status: 'active',
		description: 'Awarded for completing the leadership programme.',
		certificate_type: 'course',
		level: 'intermediate',
		duration: '5 days',
		learn_more_url: 'https://example.test/leadership',
		validity_days: 365,
		attributes: [
			{ key: 'course_hours', label: 'Course hours' },
			{ key: 'learner_name', label: 'Learner name' },
		],
		created_at: created,
		updated_at: created,
	});
	state.certificates.set('cert-draft', {
		id: 'cert-draft',
		name: 'Draft Certificate',
		status: 'draft',
		description: 'Not published yet.',
		certificate_type: 'certificate',
		level: null,
		duration: null,
		learn_more_url: null,
		validity_days: null,
		attributes: [],
		created_at: created,
		updated_at: created,
	});

	const lmsCertificates = {
		'cert-tutor': 'Tutor Leadership Certificate',
		'cert-learnpress': 'LearnPress Leadership Certificate',
		'cert-lifterlms': 'LifterLMS Leadership Certificate',
		'cert-sensei': 'Sensei Leadership Certificate',
	};

	for (const [id, name] of Object.entries(lmsCertificates)) {
		state.certificates.set(id, {
			id,
			name,
			status: 'active',
			description: `Issued from the ${name} mapping.`,
			certificate_type: 'course',
			level: 'intermediate',
			duration: null,
			learn_more_url: null,
			validity_days: 365,
			attributes: [
				{ key: 'course_hours', label: 'Course hours' },
				{ key: 'learner_name', label: 'Learner name' },
			],
			created_at: created,
			updated_at: created,
		});
	}
}

function summary(certificate) {
	return {
		id: certificate.id,
		name: certificate.name,
		status: certificate.status,
		description: certificate.description,
		certificate_type: certificate.certificate_type,
		level: certificate.level,
		duration: certificate.duration,
		learn_more_url: certificate.learn_more_url,
		validity_days: certificate.validity_days,
		blockchain_enabled: false,
		attributes: certificate.attributes,
		skills: [],
		sponsors: [],
		earning_criteria: [],
		created_at: certificate.created_at,
		updated_at: certificate.updated_at,
	};
}

function detail(certificate) {
	return {
		...summary(certificate),
		has_design: true,
		published_design_version: 1,
		design_versions: [1],
		pending_recipient_count: 0,
		request_form_enabled: false,
		unlock_form_enabled: false,
		correction_form_enabled: false,
		request_form_fields: [],
		unlock_form_fields: [],
		correction_form_fields: [],
		request_form_mode: 'manual_approval',
		correction_form_mode: 'manual_approval',
		request_form_token: null,
		correction_form_token: null,
	};
}

function recipientIssue(issue) {
	const certificate = state.certificates.get(issue.certificate_id);

	return {
		id: issue.id,
		certificate_id: issue.certificate_id,
		certificate_name: certificate ? certificate.name : 'Unknown',
		certificate_status: certificate ? certificate.status : 'archived',
		public_id: issue.public_id,
		recipient_email: issue.recipient_email,
		recipient_name: issue.recipient_name,
		status: issue.status,
		pending_reason: issue.pending_reason,
		issued_at: issue.issued_at,
		expires_at: issue.expires_at,
		created_at: issue.created_at,
		updated_at: issue.updated_at,
	};
}

function send(response, status, body) {
	const payload = JSON.stringify(body);
	response.writeHead(status, {
		'Content-Type': 'application/json; charset=utf-8',
		'Content-Length': Buffer.byteLength(payload),
	});
	response.end(payload);
}

function problem(response, status, detailText, code) {
	send(response, status, {
		type: 'about:blank',
		title: http.STATUS_CODES[status] || 'Error',
		status,
		detail: detailText,
		code,
	});
}

function readBody(request) {
	return new Promise((resolve, reject) => {
		let body = '';
		request.on('data', (chunk) => {
			body += chunk;
		});
		request.on('end', () => {
			if (!body) {
				resolve({});
				return;
			}
			try {
				resolve(JSON.parse(body));
			} catch (error) {
				reject(error);
			}
		});
		request.on('error', reject);
	});
}

function log(request, status) {
	const line = `${new Date().toISOString()} ${request.method} ${request.url} -> ${status}`;
	state.requests.push(line);
	console.log(line);
}

function authorized(request) {
	const header = request.headers.authorization || '';
	return header === `Bearer ${API_KEY}`;
}

async function handle(request, response) {
	const url = new URL(request.url, `http://localhost:${PORT}`);
	const path = url.pathname;

	if (path === '/health') {
		send(response, 200, { ok: true, workspace: WORKSPACE, issues: state.issues.size });
		return;
	}

	if (!authorized(request)) {
		problem(response, 401, 'The API key is missing or invalid.', 'unauthorized');
		return;
	}

	const body = request.method === 'POST' ? await readBody(request) : {};
	const workspace = `/api/v1/workspaces/${WORKSPACE}`;

	if (path === `${workspace}/certificates` && request.method === 'GET') {
		const search = (url.searchParams.get('search') || '').toLowerCase();
		const limit = Number(url.searchParams.get('limit') || 50);
		const offset = Number(url.searchParams.get('offset') || 0);
		const matches = [...state.certificates.values()].filter((certificate) => certificate.name.toLowerCase().includes(search));
		send(response, 200, {
			items: matches.slice(offset, offset + limit).map(summary),
			total: matches.length,
			limit,
			offset,
		});
		return;
	}

	const certificateMatch = path.match(new RegExp(`^${workspace}/certificates/([^/]+)$`));
	if (certificateMatch && request.method === 'GET') {
		const certificate = state.certificates.get(decodeURIComponent(certificateMatch[1]));
		if (!certificate) {
			problem(response, 404, 'Certificate not found.', 'not_found');
			return;
		}
		send(response, 200, detail(certificate));
		return;
	}

	const importMatch = path.match(new RegExp(`^${workspace}/certificates/([^/]+)/issues$`));
	if (importMatch && request.method === 'POST') {
		const certificateId = decodeURIComponent(importMatch[1]);
		const certificate = state.certificates.get(certificateId);
		if (!certificate) {
			problem(response, 404, 'Certificate not found.', 'not_found');
			return;
		}
		if (certificate.status !== 'active') {
			problem(response, 400, 'Publish the certificate before issuing recipients.', 'certificate_not_published');
			return;
		}

		const rows = Array.isArray(body.issues) ? body.issues : [];
		const items = [];
		const rejected = [];

		for (const row of rows) {
			const email = String(row.email || '').toLowerCase();
			const existing = [...state.issues.values()].find(
				(issue) => issue.certificate_id === certificateId && issue.recipient_email.toLowerCase() === email
			);

			if (existing) {
				rejected.push(email);
				continue;
			}

			const issue = {
				id: randomUUID(),
				certificate_id: certificateId,
				public_id: randomUUID(),
				recipient_email: email,
				recipient_name: String(row.name || ''),
				status: 'pending',
				anchor_status: 'none',
				pending_reason: 'manual_issue',
				attributes: row.attributes || {},
				design_version: 1,
				issued_at: null,
				expires_at: row.expires_at || null,
				notified_at: null,
				created_at: now(),
				updated_at: now(),
			};

			state.issues.set(issue.id, issue);
			items.push(issue);
		}

		send(response, 200, {
			items,
			rejected,
			summary: {
				total: rows.length,
				valid: items.length,
				cleaned: 0,
				warnings: 0,
				invalid: 0,
				duplicates: rejected.length,
				unverified: 0,
			},
			results: rows.map((row, index) => ({
				row: index + 1,
				input: String(row.email || ''),
				email: String(row.email || '').toLowerCase(),
				verdict: rejected.includes(String(row.email || '').toLowerCase()) ? 'duplicate' : 'valid',
				cleaned: false,
				issues: [],
			})),
		});
		return;
	}

	const listIssuesMatch = path.match(new RegExp(`^${workspace}/certificates/([^/]+)/issues$`));
	if (listIssuesMatch && request.method === 'GET') {
		const certificateId = decodeURIComponent(listIssuesMatch[1]);
		const search = (url.searchParams.get('search') || '').toLowerCase();
		const limit = Number(url.searchParams.get('limit') || 50);
		const offset = Number(url.searchParams.get('offset') || 0);
		const matches = [...state.issues.values()].filter(
			(issue) =>
				issue.certificate_id === certificateId &&
				(!search ||
					issue.recipient_email.toLowerCase().includes(search) ||
					issue.recipient_name.toLowerCase().includes(search))
		);
		send(response, 200, {
			items: matches.slice(offset, offset + limit),
			total: matches.length,
			limit,
			offset,
		});
		return;
	}

	const issueMatch = path.match(new RegExp(`^${workspace}/issues/([^/]+)$`));
	if (issueMatch && request.method === 'GET') {
		const issue = state.issues.get(decodeURIComponent(issueMatch[1]));
		if (!issue) {
			problem(response, 404, 'Issue not found.', 'not_found');
			return;
		}
		send(response, 200, issue);
		return;
	}

	const issueActionMatch = path.match(new RegExp(`^${workspace}/issues/([^/]+)/(issue|reissue)$`));
	if (issueActionMatch && request.method === 'POST') {
		const issue = state.issues.get(decodeURIComponent(issueActionMatch[1]));
		if (!issue) {
			problem(response, 404, 'Issue not found.', 'not_found');
			return;
		}

		issue.status = 'active';
		issue.issued_at = issue.issued_at || now();
		issue.notified_at = now();
		issue.updated_at = now();
		delete issue.pending_reason;
		if (issueActionMatch[2] === 'reissue') {
			issue.design_version += 1;
			issue.issued_at = now();
		}

		send(response, 200, issue);
		return;
	}

	const recipientMatch = path.match(new RegExp(`^${workspace}/recipients/([^/]+)$`));
	if (recipientMatch && request.method === 'GET') {
		const email = decodeURIComponent(recipientMatch[1]).toLowerCase();
		const issues = [...state.issues.values()].filter((issue) => issue.recipient_email.toLowerCase() === email);

		if (issues.length === 0) {
			problem(response, 404, 'Recipient not found.', 'not_found');
			return;
		}

		send(response, 200, {
			recipient_email: email,
			recipient_name: issues[0].recipient_name,
			issue_count: issues.length,
			certificate_count: new Set(issues.map((issue) => issue.certificate_id)).size,
			latest_created_at: issues[issues.length - 1].created_at,
			latest_issued_at: issues.map((issue) => issue.issued_at).filter(Boolean).sort().pop(),
			issues: issues.map(recipientIssue),
		});
		return;
	}

	problem(response, 404, `No mock route for ${request.method} ${path}.`, 'not_found');
}

seed();

const server = http.createServer((request, response) => {
	const originalEnd = response.end.bind(response);
	response.end = (...args) => {
		log(request, response.statusCode);
		return originalEnd(...args);
	};

	handle(request, response).catch((error) => {
		console.error(error);
		problem(response, 500, 'Mock API error.', 'internal_error');
	});
});

server.listen(PORT, '0.0.0.0', () => {
	console.log(`Verifycate mock API listening on http://0.0.0.0:${PORT}`);
	console.log(`Workspace: ${WORKSPACE}  API key: ${API_KEY}`);
});
