let loadedVideos = 0;
let scannedApiVideos = 0;
let totalReportedVideos = 0;
let nextPageToken = '';
let loadingVideos = false;
const i18n = window.i18n || {};

document.addEventListener('DOMContentLoaded', () => {
	const loadMore = document.getElementById('loadMore');

	if (loadMore) {
		loadMore.addEventListener('click', () => loadPage(window.channelId, nextPageToken));
	}

	if (window.channelId) {
		loadPage(window.channelId);
	}
});

async function loadPage(channelId, pageToken = '') {
	if (loadingVideos) {
		return;
	}

	loadingVideos = true;
	toggleLoadMore(false);

	try {
		const params = new URLSearchParams({ channelId, pageToken });
		const response = await fetch(`query.php?${params.toString()}`);
		const result = await response.json();

		if (!response.ok || result.error) {
			throw new Error(result.error || 'Unable to load videos.');
		}

		nextPageToken = result.pageToken || '';
		scannedApiVideos += result.scannedApiVideos || result.scannedUploads || 0;
		totalReportedVideos = result.totalReportedVideos || result.totalResults || totalReportedVideos;
		renderVideos(result.foundVideos);
		updateProgress();
		updateResultsNote(result.hasMoreApiPages === true || result.hasMoreUploads === true);

		toggleLoadMore(nextPageToken !== '');
	} catch (error) {
		const totalResults = document.getElementById('totalResults');
		if (totalResults) {
			totalResults.textContent = error.message;
		}
	} finally {
		loadingVideos = false;
	}
}

function updateProgress() {
	const totalResultsNode = document.getElementById('totalResults');
	const progressbar = document.getElementById('progressbar');
	const footerProgressbar = document.getElementById('footerProgressbar');
	const boundedTotal = Math.max(totalReportedVideos, scannedApiVideos, 1);
	const percentage = Math.min(Math.round((scannedApiVideos / boundedTotal) * 100), 100);

	if (totalResultsNode) {
		totalResultsNode.textContent = t('free_videos_found', formatCount(loadedVideos));
	}

	if (progressbar) {
		progressbar.style.width = `${percentage}%`;
	}

	if (footerProgressbar) {
		footerProgressbar.style.width = `${percentage}%`;
	}
}

function updateResultsNote(hasMoreUploads) {
	const note = document.getElementById('resultsNote');
	const footer = document.getElementById('resultsFooter');
	const footerNote = document.getElementById('footerResultsNote');

	if (!note) {
		return;
	}

	let message = '';

	if (hasMoreUploads) {
		message = t('progress_more', formatCount(scannedApiVideos), formatCount(totalReportedVideos), window.channelTitle || '');
	} else if (scannedApiVideos < totalReportedVideos) {
		message = t('progress_stopped', formatCount(scannedApiVideos), formatCount(totalReportedVideos), window.channelTitle || '');
	} else {
		message = t('progress_done', formatCount(scannedApiVideos), formatCount(totalReportedVideos), window.channelTitle || '');
	}

	note.textContent = message;

	if (footerNote) {
		footerNote.textContent = message;
	}

	if (footer) {
		footer.hidden = loadedVideos === 0;
	}
}

function formatCount(value) {
	const number = Number(value) || 0;

	if (number >= 1000000) {
		return `${trimDecimal(number / 1000000)}m`;
	}

	if (number >= 1000) {
		return `${trimDecimal(number / 1000)}k`;
	}

	return `${number}`;
}

function trimDecimal(value) {
	return value.toFixed(1).replace(/\.0$/, '');
}

function toggleLoadMore(show) {
	const loadMore = document.getElementById('loadMore');

	if (loadMore) {
		loadMore.hidden = !show;
	}
}

function renderVideos(videos) {
	const list = document.getElementById('videos');

	videos.forEach((video) => {
		loadedVideos += 1;
		const url = `https://youtube.com/watch?v=${video.id}`;
		const item = document.createElement('li');
		item.id = video.id;
		item.className = 'video-card';

		const thumbnail = document.createElement('a');
		thumbnail.className = 'thumbnail';
		thumbnail.href = url;
		thumbnail.target = '_blank';
		thumbnail.rel = 'noopener noreferrer';

		const image = document.createElement('img');
		image.src = video.thumbnails?.medium?.url || video.thumbnails?.default?.url || '';
		image.alt = '';
		image.loading = 'lazy';
		thumbnail.appendChild(image);

		const date = document.createElement('time');
		date.dateTime = video.publishedAt;
		date.textContent = formatDate(video.publishedAt);
		thumbnail.appendChild(date);

		const title = document.createElement('a');
		title.className = 'video-title';
		title.href = url;
		title.target = '_blank';
		title.rel = 'noopener noreferrer';
		title.textContent = video.title;

		const copyInput = document.createElement('input');
		copyInput.type = 'text';
		copyInput.readOnly = true;
		copyInput.value = url;
		copyInput.addEventListener('click', () => copyInput.select());

		const statusRow = document.createElement('div');
		statusRow.className = 'commons-row';

		const actions = document.createElement('div');
		actions.className = 'commons-actions';

		const checkButton = document.createElement('button');
		checkButton.className = 'commons-button';
		checkButton.type = 'button';
		checkButton.innerHTML = `${commonsLogo()}<span>${t('check_commons')}</span>`;
		checkButton.addEventListener('click', () => {
			checkButton.disabled = true;
			checkWikimediaCommons(video.id, item, checkButton);
		});

		const uploadLink = document.createElement('a');
		uploadLink.className = 'upload-link';
		uploadLink.href = `https://video2commons.toolforge.org/?url=${encodeURIComponent(url)}`;
		uploadLink.target = '_blank';
		uploadLink.rel = 'noopener noreferrer';
		uploadLink.title = t('upload_title');
		uploadLink.setAttribute('aria-label', t('upload_title'));
		uploadLink.innerHTML = `
			<svg class="upload-icon" viewBox="0 0 24 24" aria-hidden="true">
				<path d="M12 3 7 8h3v6h4V8h3l-5-5Z"></path>
				<path d="M5 15h3v3h8v-3h3v5H5v-5Z"></path>
			</svg>
			<span>${t('upload')}</span>
			<svg class="external-icon" viewBox="0 0 24 24" aria-hidden="true">
				<path d="M14 4h6v6h-2V7.4l-7.3 7.3-1.4-1.4L16.6 6H14V4Z"></path>
				<path d="M5 5h6v2H7v10h10v-4h2v6H5V5Z"></path>
			</svg>
		`;
		actions.append(checkButton, uploadLink);
		statusRow.append(actions);

		item.append(thumbnail, title, copyInput, statusRow);
		list.appendChild(item);
	});
}

function formatDate(dateValue) {
	return new Intl.DateTimeFormat('en-GB', {
		day: '2-digit',
		month: 'short',
		year: 'numeric',
	}).format(new Date(dateValue));
}

async function checkWikimediaCommons(videoId, item, button, attempt = 1) {
	try {
		button.innerHTML = `${commonsLogo()}<span>${t('checking_commons')}</span>`;
		const params = new URLSearchParams({ videoId });
		const response = await fetch(`published_check.php?${params.toString()}`);
		const commonsMatch = await response.json();

		if (!response.ok || commonsMatch.confidence === 'unknown') {
			throw new Error('Commons check failed');
		}

		if (commonsMatch.matched === true) {
			const result = commonsMatch.results?.[0];
			const commonsUrl = result?.pageid
				? `https://commons.wikimedia.org/?curid=${encodeURIComponent(result.pageid)}`
				: 'https://commons.wikimedia.org/';
			const link = document.createElement('a');
			link.className = 'commons-button commons-button-match';
			link.href = commonsUrl;
			link.target = '_blank';
			link.rel = 'noopener noreferrer';
			link.setAttribute('aria-label', `${t('commons_video')} (${t('external_link')})`);
			link.innerHTML = `${commonsLogo()}<span>${t('commons_video')}</span>${externalIcon()}<span class="external-label">${t('external_link')}</span>`;
			button.replaceWith(link);
			item.classList.add('possible-commons-match');
		} else {
			button.classList.add('commons-button-no-match');
			button.disabled = true;
			button.innerHTML = `${commonsLogo()}<span>${t('no_commons_match')}</span>`;
		}
	} catch (error) {
		if (attempt < 2) {
			await wait(1200);
			return checkWikimediaCommons(videoId, item, button, attempt + 1);
		}

		button.disabled = false;
		button.innerHTML = `${commonsLogo()}<span>${t('commons_check_failed')}</span>`;
	}
}

function commonsLogo() {
	return '<img class="commons-logo" src="assets/commons-logo.svg" alt="" aria-hidden="true">';
}

function externalIcon() {
	return `
		<svg class="external-icon" viewBox="0 0 24 24" aria-hidden="true">
			<path d="M14 4h6v6h-2V7.4l-7.3 7.3-1.4-1.4L16.6 6H14V4Z"></path>
			<path d="M5 5h6v2H7v10h10v-4h2v6H5V5Z"></path>
		</svg>
	`;
}

function wait(milliseconds) {
	return new Promise((resolve) => setTimeout(resolve, milliseconds));
}

function t(key, ...values) {
	let text = i18n[key] || key;

	values.forEach((value) => {
		text = text.replace('%s', value);
	});

	return text;
}
