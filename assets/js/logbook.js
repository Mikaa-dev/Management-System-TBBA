/**
 * Logbook Module JS v2.5.3
 * Day Type aware + save/complete + CSRF + private photo URLs + Mon-Fri ordering.
 */

document.addEventListener('DOMContentLoaded', () => {
    if (typeof window.logbookData === 'undefined') return;

    renderAll();
    bindPhotoInput();
});

let activeUploadActivityId = null;
let currentUploadDate = null;
let currentUploadActIdx = null;


/* =========================================================
   BASIC HELPERS
   ========================================================= */

function weekDates() {
    return Object.keys(window.logbookData || {}).sort();
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatDateObject(dateStr) {
    const [y, m, d] = String(dateStr)
        .split('-')
        .map(Number);

    return new Date(
        y,
        (m || 1) - 1,
        d || 1
    );
}

function formatDayOnly(dateStr) {
    return formatDateObject(dateStr)
        .toLocaleDateString(
            'en-GB',
            {
                weekday: 'long'
            }
        );
}

function formatShortDate(dateStr) {
    return formatDateObject(dateStr)
        .toLocaleDateString(
            'en-GB',
            {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            }
        );
}


/* =========================================================
   ALERT HELPERS
   ========================================================= */

function hasSweetAlert() {
    return Boolean(
        window.Swal &&
        typeof window.Swal.fire === 'function'
    );
}

function fallbackNotice(
    message,
    type = 'info'
) {
    let host =
        document.getElementById(
            'logbook-fallback-notice'
        );

    if (!host) {
        host =
            document.createElement('div');

        host.id =
            'logbook-fallback-notice';

        host.style.cssText = [
            'position:fixed',
            'top:20px',
            'right:20px',
            'z-index:99999',
            'max-width:360px',
            'padding:14px 16px',
            'border-radius:10px',
            'box-shadow:0 12px 30px rgba(15,23,42,.22)',
            'font:600 13px/1.45 system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif',
            'color:#fff',
            'opacity:0',
            'transform:translateY(-8px)',
            'transition:.2s ease'
        ].join(';');

        document.body.appendChild(host);
    }

    host.textContent =
        (
            type === 'error'
                ? 'Error: '
                : ''
        )
        +
        String(
            message || 'Done'
        );

    host.style.background =
        type === 'error'
            ? '#991b1b'
            : (
                type === 'success'
                    ? '#065f46'
                    : '#0f172a'
            );

    host.style.opacity = '1';

    host.style.transform =
        'translateY(0)';

    clearTimeout(
        host._hideTimer
    );

    host._hideTimer =
        setTimeout(
            () => {
                host.style.opacity = '0';

                host.style.transform =
                    'translateY(-8px)';
            },
            2400
        );
}

function showLoading(
    title,
    text = 'Please wait a moment.'
) {
    if (!hasSweetAlert()) {
        fallbackNotice(
            title || 'Processing...'
        );

        return;
    }

    Swal.fire({
        title:
            title ||
            'Processing...',

        text,

        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,

        didOpen: () => {
            Swal.showLoading();
        }
    });
}

function closeAlert() {
    if (hasSweetAlert()) {
        Swal.close();
    }
}

function showToast(
    title,
    icon = 'success'
) {
    if (!hasSweetAlert()) {
        fallbackNotice(
            title,
            icon === 'error'
                ? 'error'
                : 'success'
        );

        return Promise.resolve();
    }

    const Toast =
        Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 1800,
            timerProgressBar: true,

            didOpen: (toast) => {
                toast.addEventListener(
                    'mouseenter',
                    Swal.stopTimer
                );

                toast.addEventListener(
                    'mouseleave',
                    Swal.resumeTimer
                );
            }
        });

    return Toast.fire({
        icon,
        title
    });
}

function showSuccess(
    title,
    text = '',
    options = {}
) {
    if (!hasSweetAlert()) {
        fallbackNotice(
            [title, text]
                .filter(Boolean)
                .join(' — '),

            'success'
        );

        return Promise.resolve();
    }

    return Swal.fire({
        title,
        text,
        icon: 'success',

        confirmButtonText:
            options.confirmButtonText
            ||
            'OK',

        confirmButtonColor:
            '#2563EB',

        timer:
            options.timer
            ||
            undefined,

        timerProgressBar:
            Boolean(
                options.timer
            ),

        showConfirmButton:
            options.showConfirmButton !== false
    });
}

function showError(
    message,
    title = 'Something went wrong'
) {
    if (!hasSweetAlert()) {
        fallbackNotice(
            message
            ||
            'Something went wrong.',

            'error'
        );

        return Promise.resolve();
    }

    return Swal.fire({
        title,

        text:
            message
            ||
            'Something went wrong.',

        icon: 'error',

        confirmButtonText:
            'OK',

        confirmButtonColor:
            '#DC2626'
    });
}

function confirmAction(
    options = {}
) {
    if (!hasSweetAlert()) {
        fallbackNotice(
            'SweetAlert2 is unavailable. Please refresh the page.',
            'error'
        );

        return Promise.resolve({
            isConfirmed: false
        });
    }

    return Swal.fire({
        title:
            options.title
            ||
            'Are you sure?',

        text:
            options.text
            ||
            '',

        icon:
            options.icon
            ||
            'warning',

        showCancelButton: true,

        confirmButtonText:
            options.confirmButtonText
            ||
            'Yes, continue',

        cancelButtonText:
            options.cancelButtonText
            ||
            'Cancel',

        confirmButtonColor:
            options.confirmButtonColor
            ||
            '#2563EB',

        cancelButtonColor:
            options.cancelButtonColor
            ||
            '#64748B',

        reverseButtons: true,
        focusCancel: true
    });
}


/* =========================================================
   API
   ========================================================= */

async function apiRequest(
    url,
    options = {}
) {
    const headers =
        new Headers(
            options.headers || {}
        );

    headers.set(
        'X-Requested-With',
        'XMLHttpRequest'
    );

    const response =
        await fetch(
            url,
            {
                credentials:
                    'same-origin',

                ...options,

                headers
            }
        );

    const raw =
        await response.text();

    let payload = null;

    try {
        payload =
            raw
                ? JSON.parse(raw)
                : {};
    }

    catch (error) {
        const preview =
            raw
                .replace(
                    /\s+/g,
                    ' '
                )
                .slice(
                    0,
                    180
                );

        throw new Error(
            preview
                ? `Server returned an invalid response (${response.status}): ${preview}`
                : `Server returned an empty response (${response.status}).`
        );
    }

    if (
        !response.ok
        ||
        payload.status !== 'success'
    ) {
        throw new Error(
            payload.message
            ||
            payload.threat
            ||
            `Request failed with status ${response.status}.`
        );
    }

    return payload;
}


/* =========================================================
   RENDER ALL
   ========================================================= */

function renderAll() {
    renderAccordion();
    renderLivePreview();
}


/* =========================================================
   DAY TYPE
   ========================================================= */

function normalizeDayType(
    value
) {
    const allowed = [
        'Working Day',
        'Leave',
        'Public Holiday',
        'Medical Leave'
    ];

    const normalized =
        String(
            value || 'Working Day'
        ).trim();

    return allowed.includes(
        normalized
    )
        ? normalized
        : 'Working Day';
}

function isWorkingDayType(
    value
) {
    return (
        normalizeDayType(
            value
        )
        ===
        'Working Day'
    );
}

function dayTypeMeta(
    value
) {
    const map = {

        'Working Day': {
            icon:
                'fa-briefcase',

            label:
                'Working Day',

            note:
                'Record office location, working hours and daily activities.'
        },


        'Leave': {
            icon:
                'fa-calendar-minus',

            label:
                'Leave',

            note:
                'No working hours or activity entry is required for this day.'
        },


        'Public Holiday': {
            icon:
                'fa-flag',

            label:
                'Public Holiday',

            note:
                'No working hours or activity entry is required for this public holiday.'
        },


        'Medical Leave': {
            icon:
                'fa-notes-medical',

            label:
                'Medical Leave',

            note:
                'No working hours or activity entry is required for this medical leave day.'
        }

    };

    return map[
        normalizeDayType(
            value
        )
    ];
}

function markDayDraft(
    dateStr
) {
    const data =
        window.logbookData?.[
            dateStr
        ];

    if (
        !data
        ||
        !window.isDraft
    ) {
        return;
    }

    data.status =
        'Draft';
}

function changeDayType(
    dateStr,
    value
) {
    const data =
        window.logbookData?.[
            dateStr
        ];

    if (!data) {
        return;
    }

    data.day_type =
        normalizeDayType(
            value
        );

    markDayDraft(
        dateStr
    );

    renderAll();

    reopenDay(
        dateStr
    );
}


/* =========================================================
   LEFT ACCORDION
   ========================================================= */

function renderAccordion() {

    const container =
        document.getElementById(
            'accordionContainer'
        );

    if (!container) {
        return;
    }

    container.innerHTML = '';


    weekDates().forEach(
        (dateStr) => {

            const data =
                window.logbookData[
                    dateStr
                ] || {};


            const dayName =
                formatDayOnly(
                    dateStr
                );


            const dayType =
                normalizeDayType(
                    data.day_type
                );


            const isWorkingDay =
                isWorkingDayType(
                    dayType
                );


            const meta =
                dayTypeMeta(
                    dayType
                );


            const isReadOnly =
                !window.isDraft;


            /* -------------------------
               STATUS
            ------------------------- */

            let statusHtml = '';


            if (
                data.status
                ===
                'Completed'
            ) {
                statusHtml = `
                    <span class="day-status status-Completed">
                        <i class="fa-solid fa-check"></i>
                        Completed
                    </span>
                `;
            }

            else if (
                data.status
                ===
                'Draft'
            ) {
                statusHtml = `
                    <span class="day-status status-Draft">
                        <i class="fa-solid fa-pen"></i>
                        Draft
                    </span>
                `;
            }

            else {
                statusHtml = `
                    <span class="day-status status-Not">
                        <i
                            class="fa-solid fa-circle"
                            style="font-size:8px;"
                        ></i>
                        Not Started
                    </span>
                `;
            }


            /* -------------------------
               DAY TYPE BADGE
            ------------------------- */

            const typeBadge =
                !isWorkingDay

                    ? `
                        <span
                            style="
                                display:inline-flex;
                                align-items:center;
                                gap:5px;
                                margin-right:8px;
                                padding:4px 9px;
                                border-radius:999px;
                                background:#EEF2FF;
                                color:#3730A3;
                                font-size:10px;
                                font-weight:800;
                            "
                        >
                            <i
                                class="fa-solid ${meta.icon}"
                            ></i>

                            ${escapeHtml(
                                meta.label
                            )}
                        </span>
                      `

                    : '';


            /* -------------------------
               ACCORDION
            ------------------------- */

            const acc =
                document.createElement(
                    'div'
                );

            acc.className =
                'day-accordion';


            const header =
                document.createElement(
                    'div'
                );

            header.className =
                'day-header';

            header.onclick = () => {
                toggleAccordion(
                    acc
                );
            };


            header.innerHTML = `

                <div>
                    <span class="day-title">
                        ${escapeHtml(
                            dayName
                        )}
                    </span>

                    <span class="day-subtitle">
                        —
                        ${escapeHtml(
                            formatShortDate(
                                dateStr
                            )
                        )}
                    </span>
                </div>


                <div
                    style="
                        display:flex;
                        align-items:center;
                    "
                >
                    ${typeBadge}

                    ${statusHtml}

                    <i
                        class="
                            fa-solid
                            fa-chevron-down
                            acc-icon
                        "
                        style="
                            color:#94A3B8;
                            font-size:12px;
                            transition:.2s;
                        "
                    ></i>
                </div>

            `;


            const body =
                document.createElement(
                    'div'
                );

            body.className =
                'day-body';

            body.id =
                'body_' +
                dateStr;


            /* -------------------------
               DAY TYPE SELECT
            ------------------------- */

            const dayTypeSelect = `

                <div class="form-group">

                    <label>
                        Day Type
                    </label>

                    <select
                        class="
                            form-control
                            type-input
                        "

                        onchange="
                            changeDayType(
                                '${dateStr}',
                                this.value
                            )
                        "

                        ${
                            isReadOnly
                                ? 'disabled'
                                : ''
                        }
                    >

                        <option
                            value="Working Day"
                            ${
                                dayType ===
                                'Working Day'
                                    ? 'selected'
                                    : ''
                            }
                        >
                            Working Day
                        </option>


                        <option
                            value="Leave"
                            ${
                                dayType ===
                                'Leave'
                                    ? 'selected'
                                    : ''
                            }
                        >
                            Leave
                        </option>


                        <option
                            value="Public Holiday"
                            ${
                                dayType ===
                                'Public Holiday'
                                    ? 'selected'
                                    : ''
                            }
                        >
                            Public Holiday
                        </option>


                        <option
                            value="Medical Leave"
                            ${
                                dayType ===
                                'Medical Leave'
                                    ? 'selected'
                                    : ''
                            }
                        >
                            Medical Leave
                        </option>

                    </select>

                </div>
            `;


            /* -------------------------
               WORKING DAY FIELDS
            ------------------------- */

            const workingFields = `

                <div
                    class="form-group"
                    style="flex:2;"
                >

                    <label>
                        Office Location
                    </label>

                    <input
                        type="text"

                        class="
                            form-control
                            loc-input
                        "

                        value="${
                            escapeHtml(
                                data.location
                                ||
                                ''
                            )
                        }"

                        placeholder="
                            e.g. HQ Cyberjaya Office
                        "

                        oninput="
                            updateData(
                                '${dateStr}',
                                'location',
                                this.value
                            )
                        "

                        ${
                            isReadOnly
                                ? 'disabled'
                                : ''
                        }
                    >

                </div>


                <div class="form-group">

                    <label>
                        Start Time
                    </label>

                    <input
                        type="time"

                        class="
                            form-control
                            time-input
                        "

                        value="${
                            escapeHtml(
                                data.start_time
                                ||
                                '08:30:00'
                            )
                        }"

                        oninput="
                            updateData(
                                '${dateStr}',
                                'start_time',
                                this.value
                            )
                        "

                        ${
                            isReadOnly
                                ? 'disabled'
                                : ''
                        }
                    >

                </div>


                <div class="form-group">

                    <label>
                        End Time
                    </label>

                    <input
                        type="time"

                        class="
                            form-control
                            time-input
                        "

                        value="${
                            escapeHtml(
                                data.end_time
                                ||
                                '17:30:00'
                            )
                        }"

                        oninput="
                            updateData(
                                '${dateStr}',
                                'end_time',
                                this.value
                            )
                        "

                        ${
                            isReadOnly
                                ? 'disabled'
                                : ''
                        }
                    >

                </div>


                ${dayTypeSelect}
            `;


            /* -------------------------
               NON WORKING DAY
            ------------------------- */

            const nonWorkingFields = `

                ${dayTypeSelect}


                <div
                    style="
                        flex:1 1 360px;
                        min-width:260px;
                        display:flex;
                        align-items:center;
                        gap:12px;
                        padding:10px 12px;
                        border:1px solid #C7D2FE;
                        background:#EEF2FF;
                        border-radius:8px;
                        color:#3730A3;
                    "
                >

                    <i
                        class="
                            fa-solid
                            ${meta.icon}
                        "
                        style="
                            font-size:17px;
                        "
                    ></i>


                    <div>

                        <div
                            style="
                                font-size:12px;
                                font-weight:800;
                            "
                        >
                            ${escapeHtml(
                                meta.label
                            )}
                        </div>


                        <div
                            style="
                                font-size:11px;
                                margin-top:2px;
                                color:#4F46E5;
                            "
                        >
                            ${escapeHtml(
                                meta.note
                            )}
                        </div>

                    </div>

                </div>
            `;


            const nonWorkingNotice = `

                <div
                    style="
                        margin-top:4px;
                        padding:18px;
                        border:1px dashed #C7D2FE;
                        border-radius:10px;
                        background:#F8FAFF;
                        text-align:center;
                    "
                >

                    <i
                        class="
                            fa-solid
                            ${meta.icon}
                        "
                        style="
                            font-size:24px;
                            color:#4F46E5;
                            margin-bottom:8px;
                        "
                    ></i>


                    <div
                        style="
                            font-size:14px;
                            font-weight:800;
                            color:#312E81;
                        "
                    >
                        ${escapeHtml(
                            meta.label
                        )}
                    </div>


                    <div
                        style="
                            font-size:12px;
                            color:#64748B;
                            margin-top:4px;
                        "
                    >
                        Working hours and activities are hidden
                        for this day type.
                    </div>


                    ${
                        (
                            data.activities
                            ||
                            []
                        ).length

                            ? `
                                <div
                                    style="
                                        font-size:10px;
                                        color:#94A3B8;
                                        margin-top:8px;
                                    "
                                >
                                    Existing activity data is retained
                                    and will reappear if you switch back
                                    to Working Day.
                                </div>
                              `

                            : ''
                    }

                </div>
            `;


            /* -------------------------
               BODY
            ------------------------- */

            body.innerHTML = `

                <div class="form-row">

                    ${
                        isWorkingDay
                            ? workingFields
                            : nonWorkingFields
                    }

                </div>


                ${
                    isWorkingDay

                        ? `<div id="activities_${dateStr}"></div>`

                        : nonWorkingNotice
                }


                ${
                    !isReadOnly

                        ? `

                            ${
                                isWorkingDay

                                    ? `
                                        <div
                                            style="
                                                margin-top:16px;
                                            "
                                        >

                                            <button
                                                type="button"

                                                class="
                                                    btn
                                                    btn-secondary
                                                "

                                                onclick="
                                                    addActivity(
                                                        '${dateStr}'
                                                    )
                                                "

                                                style="
                                                    padding:8px 16px;
                                                    border-radius:6px;
                                                    background:#fff;
                                                    border:1px solid #CBD5E1;
                                                    color:#2563EB;
                                                    font-weight:600;
                                                    cursor:pointer;
                                                "
                                            >
                                                <i
                                                    class="
                                                        fa-solid
                                                        fa-plus
                                                    "
                                                ></i>

                                                Add Another Activity
                                            </button>

                                        </div>
                                      `

                                    : ''
                            }


                            <div
                                style="
                                    margin-top:24px;
                                    padding-top:16px;
                                    border-top:1px solid #E2E8F0;
                                    text-align:right;
                                    display:flex;
                                    justify-content:flex-end;
                                    gap:10px;
                                "
                            >

                                <button
                                    type="button"

                                    class="
                                        btn
                                        btn-secondary
                                    "

                                    onclick="
                                        saveDay(
                                            '${dateStr}',
                                            'Draft'
                                        )
                                    "

                                    style="
                                        padding:10px 20px;
                                        border-radius:6px;
                                        background:#fff;
                                        border:1px solid #CBD5E1;
                                        color:#475569;
                                        font-weight:600;
                                        cursor:pointer;
                                    "
                                >
                                    Save Draft
                                </button>


                                <button
                                    type="button"

                                    class="
                                        btn
                                        btn-primary
                                    "

                                    onclick="
                                        saveDay(
                                            '${dateStr}',
                                            'Completed'
                                        )
                                    "

                                    style="
                                        padding:10px 20px;
                                        border-radius:6px;
                                        background:#2563EB;
                                        border:none;
                                        color:#fff;
                                        font-weight:600;
                                        cursor:pointer;
                                    "
                                >
                                    Complete Day
                                </button>

                            </div>

                          `

                        : ''
                }

            `;


            acc.appendChild(
                header
            );

            acc.appendChild(
                body
            );

            container.appendChild(
                acc
            );


            if (isWorkingDay) {
                renderActivities(
                    dateStr,
                    isReadOnly
                );
            }

        }
    );
}


/* =========================================================
   ACTIVITIES
   ========================================================= */

function renderActivities(
    dateStr,
    isReadOnly
) {

    const container =
        document.getElementById(
            'activities_' +
            dateStr
        );

    if (!container) {
        return;
    }


    container.innerHTML = '';


    const activities =
        window.logbookData?.[
            dateStr
        ]?.activities
        ||
        [];


    activities.forEach(
        (
            activity,
            actIdx
        ) => {

            const actions =
                activity.actions
                ||
                [];


            const photos =
                activity.photos
                ||
                [];


            /* -------------------------
               ACTIONS
            ------------------------- */

            const actionsHtml =
                actions
                    .map(
                        (
                            action,
                            actionIdx
                        ) => `

                            <div class="action-item">

                                <i
                                    class="
                                        fa-solid
                                        fa-grip-vertical
                                        action-drag
                                    "
                                ></i>


                                <input
                                    type="text"

                                    class="
                                        form-control
                                    "

                                    value="${
                                        escapeHtml(
                                            action.description
                                            ||
                                            ''
                                        )
                                    }"

                                    placeholder="
                                        Describe what you did...
                                    "

                                    oninput="
                                        updateAction(
                                            '${dateStr}',
                                            ${actIdx},
                                            ${actionIdx},
                                            this.value
                                        )
                                    "

                                    ${
                                        isReadOnly
                                            ? 'disabled'
                                            : ''
                                    }
                                >


                                ${
                                    !isReadOnly

                                        ? `
                                            <i
                                                class="
                                                    fa-solid
                                                    fa-trash-can
                                                "

                                                style="
                                                    color:#EF4444;
                                                    cursor:pointer;
                                                "

                                                onclick="
                                                    removeAction(
                                                        '${dateStr}',
                                                        ${actIdx},
                                                        ${actionIdx}
                                                    )
                                                "
                                            ></i>
                                          `

                                        : ''
                                }

                            </div>

                          `
                    )
                    .join('');


            /* -------------------------
               PHOTOS
            ------------------------- */

            const photosHtml =
                photos
                    .map(
                        (photo) => {

                            const src =
                                photo.url
                                ||
                                photo.photo_path
                                ||
                                '';


                            return `

                                <div class="photo-thumb">

                                    <img
                                        src="${escapeHtml(src)}"
                                        alt="Logbook photo"
                                    >


                                    ${
                                        !isReadOnly

                                            ? `
                                                <div
                                                    class="
                                                        photo-remove
                                                    "

                                                    onclick="
                                                        removePhoto(
                                                            '${dateStr}',
                                                            ${actIdx},
                                                            ${Number(
                                                                photo.id
                                                            )}
                                                        )
                                                    "
                                                >
                                                    <i
                                                        class="
                                                            fa-solid
                                                            fa-xmark
                                                        "
                                                    ></i>
                                                </div>
                                              `

                                            : ''
                                    }

                                </div>
                            `;
                        }
                    )
                    .join('');


            /* -------------------------
               BLOCK
            ------------------------- */

            const block =
                document.createElement(
                    'div'
                );

            block.className =
                'activity-block';


            block.innerHTML = `

                ${
                    !isReadOnly

                        ? `
                            <div
                                style="
                                    position:absolute;
                                    top:16px;
                                    right:16px;
                                    cursor:pointer;
                                    color:#EF4444;
                                "

                                onclick="
                                    removeActivity(
                                        '${dateStr}',
                                        ${actIdx}
                                    )
                                "
                            >
                                <i
                                    class="
                                        fa-solid
                                        fa-trash
                                    "
                                ></i>
                            </div>
                          `

                        : ''
                }


                <div class="activity-header">


                    <div class="activity-title-wrap">

                        <label
                            style="
                                display:block;
                                font-size:12px;
                                color:var(--text-muted);
                                margin-bottom:6px;
                                font-weight:600;
                            "
                        >
                            ${actIdx + 1}.
                            Highlight / Activity Title
                        </label>


                        <input
                            type="text"

                            class="
                                form-control
                            "

                            value="${
                                escapeHtml(
                                    activity.title
                                    ||
                                    ''
                                )
                            }"

                            placeholder="
                                e.g. FINALIZE TENTATIVE
                            "

                            oninput="
                                updateActivityTitle(
                                    '${dateStr}',
                                    ${actIdx},
                                    this.value
                                )
                            "

                            ${
                                isReadOnly
                                    ? 'disabled'
                                    : ''
                            }
                        >


                        <div
                            style="
                                margin-top:16px;
                            "
                        >

                            <label
                                style="
                                    display:block;
                                    font-size:12px;
                                    color:var(--text-muted);
                                    margin-bottom:6px;
                                    font-weight:600;
                                "
                            >
                                Action List
                            </label>


                            <div
                                class="
                                    action-list
                                "

                                id="
                                    action_list_${dateStr}_${actIdx}
                                "
                            >
                                ${actionsHtml}
                            </div>


                            ${
                                !isReadOnly

                                    ? `
                                        <button
                                            type="button"

                                            onclick="
                                                addAction(
                                                    '${dateStr}',
                                                    ${actIdx}
                                                )
                                            "

                                            style="
                                                margin-top:10px;
                                                background:none;
                                                border:1px solid #38BDF8;
                                                color:#0284C7;
                                                padding:4px 12px;
                                                border-radius:4px;
                                                font-size:11px;
                                                font-weight:600;
                                                cursor:pointer;
                                            "
                                        >
                                            <i
                                                class="
                                                    fa-solid
                                                    fa-plus
                                                "
                                            ></i>

                                            Add Action
                                        </button>
                                      `

                                    : ''
                            }

                        </div>

                    </div>


                    <div class="activity-photos-wrap">

                        <label
                            style="
                                display:block;
                                font-size:11px;
                                color:var(--text-muted);
                                margin-bottom:6px;
                            "
                        >
                            Upload Photos (Optional)
                        </label>


                        ${
                            !isReadOnly

                                ? `
                                    <button
                                        type="button"

                                        onclick="
                                            preparePhotoUpload(
                                                '${dateStr}',
                                                ${actIdx}
                                            )
                                        "

                                        style="
                                            background:#F1F5F9;
                                            border:none;
                                            color:#475569;
                                            padding:10px;
                                            border-radius:4px;
                                            font-size:11px;
                                            cursor:pointer;
                                            width:100%;
                                        "
                                    >
                                        <i
                                            class="
                                                fa-solid
                                                fa-camera
                                            "
                                            style="
                                                font-size:16px;
                                                display:block;
                                                margin-bottom:4px;
                                            "
                                        ></i>

                                        ${
                                            activity.id
                                                ? 'Upload Photo'
                                                : 'Save & Upload Photo'
                                        }
                                    </button>
                                  `

                                : ''
                        }


                        <div class="photo-grid">
                            ${photosHtml}
                        </div>

                    </div>

                </div>
            `;


            container.appendChild(
                block
            );

        }
    );
}


/* =========================================================
   ACCORDION CONTROL
   ========================================================= */

function toggleAccordion(
    accDiv
) {

    document
        .querySelectorAll(
            '.day-accordion'
        )
        .forEach(
            (el) => {

                if (
                    el === accDiv
                ) {
                    return;
                }


                el
                    .querySelector(
                        '.day-body'
                    )
                    ?.classList
                    .remove(
                        'active'
                    );


                el
                    .querySelector(
                        '.day-header'
                    )
                    ?.classList
                    .remove(
                        'active'
                    );


                const icon =
                    el.querySelector(
                        '.acc-icon'
                    );


                if (icon) {
                    icon.style.transform =
                        'rotate(0deg)';
                }

            }
        );


    const body =
        accDiv.querySelector(
            '.day-body'
        );


    const header =
        accDiv.querySelector(
            '.day-header'
        );


    const icon =
        accDiv.querySelector(
            '.acc-icon'
        );


    if (
        !body
        ||
        !header
    ) {
        return;
    }


    const opening =
        !body
            .classList
            .contains(
                'active'
            );


    body
        .classList
        .toggle(
            'active',
            opening
        );


    header
        .classList
        .toggle(
            'active',
            opening
        );


    if (icon) {
        icon.style.transform =
            opening
                ? 'rotate(180deg)'
                : 'rotate(0deg)';
    }
}


function reopenDay(
    dateStr
) {

    const body =
        document.getElementById(
            'body_' +
            dateStr
        );


    if (!body) {
        return;
    }


    body
        .classList
        .add(
            'active'
        );


    body
        .parentElement
        ?.querySelector(
            '.day-header'
        )
        ?.classList
        .add(
            'active'
        );


    const icon =
        body
            .parentElement
            ?.querySelector(
                '.acc-icon'
            );


    if (icon) {
        icon.style.transform =
            'rotate(180deg)';
    }
}


/* =========================================================
   DATA MANIPULATION
   ========================================================= */

function updateData(
    dateStr,
    key,
    value
) {

    const data =
        window.logbookData?.[
            dateStr
        ];


    if (!data) {
        return;
    }


    if (
        key ===
        'day_type'
    ) {

        changeDayType(
            dateStr,
            value
        );

        return;
    }


    data[key] =
        value;


    markDayDraft(
        dateStr
    );


    renderLivePreview();
}


function updateActivityTitle(
    dateStr,
    actIdx,
    value
) {

    const data =
        window.logbookData?.[
            dateStr
        ];


    if (
        !data
        ?.activities
        ?.[actIdx]
    ) {
        return;
    }


    data
        .activities[
            actIdx
        ]
        .title =
        value;


    data.status =
        'Draft';


    renderLivePreview();
}


function updateAction(
    dateStr,
    actIdx,
    actionIdx,
    value
) {

    const data =
        window.logbookData?.[
            dateStr
        ];


    if (
        !data
        ?.activities
        ?.[actIdx]
        ?.actions
        ?.[actionIdx]
    ) {
        return;
    }


    data
        .activities[
            actIdx
        ]
        .actions[
            actionIdx
        ]
        .description =
        value;


    data.status =
        'Draft';


    renderLivePreview();
}


function addActivity(
    dateStr
) {

    const data =
        window.logbookData?.[
            dateStr
        ];


    if (
        !data
        ||
        !isWorkingDayType(
            data.day_type
        )
    ) {
        return;
    }


    data.activities ||= [];


    data.activities.push({
        title: '',

        actions: [
            {
                description: ''
            }
        ],

        photos: []
    });


    data.status =
        'Draft';


    renderAll();

    reopenDay(
        dateStr
    );
}


function removeActivity(
    dateStr,
    actIdx
) {

    const data =
        window.logbookData?.[
            dateStr
        ];


    if (
        !data
        ?.activities
    ) {
        return;
    }


    data
        .activities
        .splice(
            actIdx,
            1
        );


    data.status =
        'Draft';


    renderAll();

    reopenDay(
        dateStr
    );
}


function addAction(
    dateStr,
    actIdx
) {

    const activity =
        window.logbookData?.[
            dateStr
        ]
        ?.activities
        ?.[actIdx];


    if (!activity) {
        return;
    }


    activity.actions ||= [];


    activity.actions.push({
        description: ''
    });


    window.logbookData[
        dateStr
    ].status =
        'Draft';


    renderAll();

    reopenDay(
        dateStr
    );
}


function removeAction(
    dateStr,
    actIdx,
    actionIdx
) {

    const activity =
        window.logbookData?.[
            dateStr
        ]
        ?.activities
        ?.[actIdx];


    if (
        !activity
        ?.actions
    ) {
        return;
    }


    activity
        .actions
        .splice(
            actionIdx,
            1
        );


    window.logbookData[
        dateStr
    ].status =
        'Draft';


    renderAll();

    reopenDay(
        dateStr
    );
}


/* =========================================================
   LIVE PREVIEW
   ========================================================= */

function renderLivePreview() {

    const container =
        document.getElementById(
            'livePreviewContainer'
        );


    if (!container) {
        return;
    }


    const user =
        window.userData || {};


    const dates =
        weekDates();


    /*
     * Cari Working Day pertama
     * yang ada location/time.
     *
     * Jadi kalau Monday Public Holiday,
     * header report boleh guna Tuesday.
     */

    const primaryWorkingDate =
        dates.find(
            (dateStr) => {

                const day =
                    window.logbookData?.[
                        dateStr
                    ]
                    ||
                    {};


                return (
                    isWorkingDayType(
                        day.day_type
                    )
                    &&
                    (
                        day.location
                        ||
                        day.start_time
                        ||
                        day.end_time
                    )
                );
            }
        );


    const primaryWorkingLog =
        primaryWorkingDate

            ? (
                window.logbookData[
                    primaryWorkingDate
                ]
                ||
                {}
            )

            : {};


    const formatTime =
        (
            timeStr
        ) => {

            if (!timeStr) {
                return '';
            }


            const [
                h,
                m
            ] =
                String(
                    timeStr
                ).split(':');


            const hour =
                parseInt(
                    h,
                    10
                );


            if (
                Number.isNaN(
                    hour
                )
            ) {
                return '';
            }


            const ampm =
                hour >= 12
                    ? 'P.M.'
                    : 'A.M.';


            const h12 =
                hour % 12
                ||
                12;


            return `${h12}:${m || '00'} ${ampm}`;
        };


    const workingHours = [

        formatTime(
            primaryWorkingLog.start_time
        ),

        formatTime(
            primaryWorkingLog.end_time
        )

    ]
        .filter(Boolean)
        .join(' - ');


    /* -------------------------
       REPORT HEADER
    ------------------------- */

    let html = `

        <div class="preview-header">

            <img
                src="assets/images/pdflogo.png"

                onerror="
                    this.style.display='none'
                "

                alt="Company logo"
            >


            <div class="preview-title">

                WEEKLY REPORT

                <br>

                ${escapeHtml(
                    formatShortDate(
                        window.weekStart
                    )
                )}

                -

                ${escapeHtml(
                    formatShortDate(
                        window.weekEnd
                    ).toUpperCase()
                )}

            </div>

        </div>


        <table class="preview-info-table">

            <tr>

                <th>
                    DEPARTMENT
                </th>

                <td>
                    ${escapeHtml(
                        user.department
                        ||
                        'N/A'
                    )}
                </td>


                <th>
                    DATE
                </th>

                <td>

                    ${escapeHtml(
                        formatShortDate(
                            window.weekStart
                        )
                    )}

                    -

                    ${escapeHtml(
                        formatShortDate(
                            window.weekEnd
                        ).toUpperCase()
                    )}

                </td>

            </tr>


            <tr>

                <th>
                    NAME
                </th>

                <td colspan="3">
                    ${escapeHtml(
                        user.name
                        ||
                        'N/A'
                    )}
                </td>

            </tr>


            <tr>

                <th>
                    OFFICE LOCATION
                </th>

                <td colspan="3">
                    ${escapeHtml(
                        primaryWorkingLog.location
                        ||
                        'N/A'
                    )}
                </td>

            </tr>


            <tr>

                <th>
                    WORKING HOURS
                </th>

                <td colspan="3">
                    ${escapeHtml(
                        workingHours
                        ||
                        'N/A'
                    )}
                </td>

            </tr>

        </table>
    `;


    /* -------------------------
       REPORT DAYS
    ------------------------- */

    dates.forEach(
        (dateStr) => {

            const data =
                window.logbookData[
                    dateStr
                ]
                ||
                {};


            const dayType =
                normalizeDayType(
                    data.day_type
                );


            const meta =
                dayTypeMeta(
                    dayType
                );


            const activities =
                data.activities
                ||
                [];


            const dayHeader =
                `${escapeHtml(
                    formatDayOnly(
                        dateStr
                    ).toUpperCase()
                )}, ${escapeHtml(
                    formatShortDate(
                        dateStr
                    ).toUpperCase()
                )}`;


            /* =========================
               NON WORKING DAY
            ========================= */

            if (
                !isWorkingDayType(
                    dayType
                )
            ) {

                html += `

                    <div class="preview-day">

                        <div class="preview-day-header">
                            ${dayHeader}
                        </div>


                        <table class="preview-act-table">

                            <tr>

                                <td
                                    class="
                                        preview-act-num
                                    "
                                    style="
                                        width:5%;
                                    "
                                >

                                    <i
                                        class="
                                            fa-solid
                                            ${meta.icon}
                                        "
                                        style="
                                            color:#4F46E5;
                                        "
                                    ></i>

                                </td>


                                <td>

                                    <div class="preview-act-title">
                                        ${escapeHtml(
                                            meta.label
                                        )}
                                    </div>


                                    <div
                                        style="
                                            font-size:9px;
                                            color:#64748B;
                                            margin-top:3px;
                                        "
                                    >
                                        ${escapeHtml(
                                            meta.note
                                        )}
                                    </div>

                                </td>

                            </tr>

                        </table>

                    </div>
                `;


                return;
            }


            /* =========================
               WORKING DAY
            ========================= */

            const renderableActivities =
                activities.filter(
                    (activity) => {

                        const hasTitle =
                            String(
                                activity.title || ''
                            ).trim() !== '';


                        const hasAction =
                            (
                                activity.actions || []
                            ).some(
                                (action) =>
                                    String(
                                        action.description || ''
                                    ).trim() !== ''
                            );


                        const hasPhoto =
                            (
                                activity.photos || []
                            ).length > 0;


                        return (
                            hasTitle
                            ||
                            hasAction
                            ||
                            hasPhoto
                        );
                    }
                );


            if (
                !renderableActivities.length
            ) {
                return;
            }


            const rows =
                renderableActivities
                    .map(
                        (
                            activity,
                            actIdx
                        ) => {

                            const actionItems =
                                (
                                    activity.actions
                                    ||
                                    []
                                )

                                    .filter(
                                        (action) =>
                                            String(
                                                action.description
                                                ||
                                                ''
                                            ).trim() !== ''
                                    )

                                    .map(
                                        (action) => `
                                            <li>
                                                <span>
                                                    ${escapeHtml(
                                                        action.description
                                                    )}
                                                </span>
                                            </li>
                                        `
                                    )

                                    .join('');


                            const actionsHtml =
                                actionItems

                                    ? `
                                        <div
                                            style="
                                                color:#D92B2B;
                                                font-weight:bold;
                                                font-size:9px;
                                                margin-top:5px;
                                            "
                                        >
                                            ACTION LIST:
                                        </div>

                                        <ul class="preview-act-actions">
                                            ${actionItems}
                                        </ul>
                                      `

                                    : '';


                            const photosHtml =
                                (
                                    activity.photos
                                    ||
                                    []
                                ).length

                                    ? `
                                        <div class="preview-photos">

                                            ${
                                                activity
                                                    .photos
                                                    .map(
                                                        (photo) => {

                                                            const src =
                                                                photo.url
                                                                ||
                                                                photo.photo_path
                                                                ||
                                                                '';


                                                            return `
                                                                <img
                                                                    src="${escapeHtml(src)}"
                                                                    alt="Logbook photo"
                                                                >
                                                            `;
                                                        }
                                                    )
                                                    .join('')
                                            }

                                        </div>
                                      `

                                    : '';


                            return `

                                <tr>

                                    <td class="preview-act-num">
                                        ${actIdx + 1}
                                    </td>


                                    <td>

                                        <div class="preview-act-title">
                                            ${escapeHtml(
                                                activity.title
                                                ||
                                                ''
                                            )}
                                        </div>


                                        ${actionsHtml}

                                        ${photosHtml}

                                    </td>

                                </tr>
                            `;
                        }
                    )

                    .join('');


            html += `

                <div class="preview-day">

                    <div class="preview-day-header">
                        ${dayHeader}
                    </div>


                    <table class="preview-act-table">
                        ${rows}
                    </table>

                </div>
            `;

        }
    );


    container.innerHTML =
        html;
}


/* =========================================================
   SAVE
   ========================================================= */

async function saveDayRequest(
    dateStr,
    forceStatus
) {

    const source =
        window.logbookData?.[
            dateStr
        ];


    if (!source) {
        throw new Error(
            'The selected day could not be found.'
        );
    }


    const payload =
        JSON.parse(
            JSON.stringify(
                source
            )
        );


    payload.status =
        forceStatus;


    payload.report_id =
        Number(
            window.reportId
        );


    payload.activity_date =
        dateStr;


    const result =
        await apiRequest(
            'index.php?action=logbook_save_entry',

            {
                method:
                    'POST',

                headers: {
                    'Content-Type':
                        'application/json',

                    'X-CSRF-Token':
                        window.csrfToken
                        ||
                        ''
                },

                body:
                    JSON.stringify(
                        payload
                    )
            }
        );


    if (
        result.data
        ?.structured_data
    ) {

        window.logbookData =
            result
                .data
                .structured_data;
    }


    return result;
}


async function saveDay(
    dateStr,
    forceStatus
) {

    showLoading(
        forceStatus ===
        'Completed'

            ? 'Completing day...'

            : 'Saving draft...'
    );


    try {

        await saveDayRequest(
            dateStr,
            forceStatus
        );


        renderAll();


        reopenDay(
            dateStr
        );


        if (
            forceStatus ===
            'Completed'
        ) {

            await showSuccess(
                'Day completed!',

                'This day has been marked as completed.',

                {
                    timer:
                        1400,

                    showConfirmButton:
                        false
                }
            );
        }

        else {
            await showToast(
                'Draft saved'
            );
        }

    }

    catch (error) {

        console.error(
            error
        );


        await showError(
            error.message
        );
    }
}


async function saveAllDrafts(
    showFeedback = true
) {

    if (showFeedback) {
        showLoading(
            'Saving all days...'
        );
    }


    try {

        let latestData =
            null;


        for (
            const dateStr
            of weekDates()
        ) {

            const currentStatus =
                window.logbookData?.[
                    dateStr
                ]?.status
                ||
                'Not Started';


            const result =
                await saveDayRequest(
                    dateStr,
                    currentStatus
                );


            latestData =
                result.data
                    ?.structured_data
                ||
                latestData;
        }


        if (latestData) {
            window.logbookData =
                latestData;
        }


        renderAll();


        if (showFeedback) {
            await showToast(
                'All drafts saved'
            );
        }


        return true;
    }

    catch (error) {

        console.error(
            error
        );


        if (showFeedback) {
            await showError(
                error.message
            );
        }


        throw error;
    }
}


/* =========================================================
   SUBMIT WEEKLY REPORT
   ========================================================= */

async function submitFinalReport() {

    const result =
        await confirmAction({

            title:
                'Submit Weekly Report?',

            text:
                "Your current changes will be saved first. After submission, you won't be able to edit entries or photos.",

            icon:
                'warning',

            confirmButtonColor:
                '#10B981',

            cancelButtonColor:
                '#d33',

            confirmButtonText:
                'Yes, submit it!'

        });


    if (
        !result.isConfirmed
    ) {
        return;
    }


    showLoading(
        'Saving and submitting...'
    );


    try {

        await saveAllDrafts(
            false
        );


        const formData =
            new FormData();


        formData.append(
            'report_id',
            String(
                window.reportId
            )
        );


        formData.append(
            'csrf_token',
            window.csrfToken
            ||
            ''
        );


        const response =
            await apiRequest(
                'index.php?action=logbook_submit',

                {
                    method:
                        'POST',

                    body:
                        formData
                }
            );


        await showSuccess(

            'Weekly report submitted!',

            response.message
            ||
            'Your weekly report has been submitted successfully.',

            {
                confirmButtonText:
                    'Done'
            }
        );


        window.location.reload();

    }

    catch (error) {

        console.error(
            error
        );


        await showError(
            error.message
        );
    }
}


/* =========================================================
   PREPARE PHOTO UPLOAD
   ========================================================= */

async function preparePhotoUpload(
    dateStr,
    actIdx
) {

    try {

        let activity =
            window.logbookData?.[
                dateStr
            ]
            ?.activities
            ?.[actIdx];


        if (!activity) {
            throw new Error(
                'Activity not found.'
            );
        }


        /*
         * Activity baru belum ada
         * database ID.
         *
         * Save day dahulu.
         */

        if (!activity.id) {

            showLoading(
                'Saving activity first...'
            );


            await saveDayRequest(
                dateStr,
                'Draft'
            );


            renderAll();


            reopenDay(
                dateStr
            );


            activity =
                window.logbookData?.[
                    dateStr
                ]
                ?.activities
                ?.[actIdx];


            if (
                !activity?.id
            ) {
                throw new Error(
                    'The activity could not be saved before photo upload.'
                );
            }
        }


        activeUploadActivityId =
            Number(
                activity.id
            );


        currentUploadDate =
            dateStr;


        currentUploadActIdx =
            actIdx;


        closeAlert();


        const input =
            document.getElementById(
                'globalPhotoInput'
            );


        if (!input) {
            throw new Error(
                'Photo input is unavailable.'
            );
        }


        input.click();

    }

    catch (error) {

        console.error(
            error
        );


        await showError(
            error.message
        );
    }
}


/* =========================================================
   PHOTO INPUT
   ========================================================= */

function bindPhotoInput() {

    const input =
        document.getElementById(
            'globalPhotoInput'
        );


    if (
        !input
        ||
        input.dataset.logbookBound === '1'
    ) {
        return;
    }


    input.dataset.logbookBound =
        '1';


    input.addEventListener(
        'change',

        async function () {

            if (
                !this.files?.[0]
                ||
                !activeUploadActivityId
            ) {
                return;
            }


            const file =
                this.files[0];


            const formData =
                new FormData();


            formData.append(
                'photo',
                file
            );


            formData.append(
                'activity_id',
                String(
                    activeUploadActivityId
                )
            );


            formData.append(
                'csrf_token',
                window.csrfToken
                ||
                ''
            );


            showLoading(
                'Uploading photo...'
            );


            try {

                const response =
                    await apiRequest(
                        'index.php?action=logbook_upload_photo',

                        {
                            method:
                                'POST',

                            body:
                                formData
                        }
                    );


                const activity =
                    window.logbookData?.[
                        currentUploadDate
                    ]
                    ?.activities
                    ?.[currentUploadActIdx];


                if (activity) {

                    activity.photos ||= [];


                    activity.photos.push({

                        id:
                            response.data.id,

                        photo_path:
                            response.data.path,

                        url:
                            response.data.url

                    });
                }


                renderAll();


                reopenDay(
                    currentUploadDate
                );


                await showToast(
                    'Photo uploaded'
                );

            }

            catch (error) {

                console.error(
                    error
                );


                await showError(
                    error.message
                );
            }

            finally {

                this.value =
                    '';


                activeUploadActivityId =
                    null;


                currentUploadDate =
                    null;


                currentUploadActIdx =
                    null;
            }

        }
    );
}


/* =========================================================
   REMOVE PHOTO
   ========================================================= */

async function removePhoto(
    dateStr,
    actIdx,
    photoId
) {

    const result =
        await confirmAction({

            title:
                'Remove Photo?',

            text:
                'This photo will be deleted from the logbook.',

            icon:
                'warning',

            confirmButtonText:
                'Yes, remove'

        });


    if (
        !result.isConfirmed
    ) {
        return;
    }


    const formData =
        new FormData();


    formData.append(
        'photo_id',
        String(
            photoId
        )
    );


    formData.append(
        'csrf_token',
        window.csrfToken
        ||
        ''
    );


    showLoading(
        'Removing photo...'
    );


    try {

        await apiRequest(
            'index.php?action=logbook_remove_photo',

            {
                method:
                    'POST',

                body:
                    formData
            }
        );


        const activity =
            window.logbookData?.[
                dateStr
            ]
            ?.activities
            ?.[actIdx];


        if (activity) {

            activity.photos =
                (
                    activity.photos
                    ||
                    []
                )
                    .filter(
                        (photo) =>
                            Number(
                                photo.id
                            )
                            !==
                            Number(
                                photoId
                            )
                    );
        }


        renderAll();


        reopenDay(
            dateStr
        );


        await showToast(
            'Photo removed'
        );

    }

    catch (error) {

        console.error(
            error
        );


        await showError(
            error.message
        );
    }
}