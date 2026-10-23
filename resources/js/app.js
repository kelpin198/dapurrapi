import "./bootstrap";

const authTokenKey = "dapurRapiToken";
const authUserKey = "dapurRapiUser";
const pendingApprovalStorageKey = "dapurRapiPendingApprovalIds";
const restaurantLogoMaxBytes = 1_500_000;
let currentTransactionPage = 1;
let latestKnownAdminTransactionId = 0;
let adminOrderWatchInitialized = false;
let adminOrderPollingTimer = null;
let userApprovalPollingTimer = null;
let userApprovalPollInProgress = false;
let recentCarouselPage = 0;
let customerReviewCount = 0;
const menuImageCache = new Map();
const menuFallbackImages = {
    makanan: "/images/nasi-goreng.jpg",
    minuman: "/images/es-jeruk.jpg",
};
window.dapurRapiFoods = [];
window.dapurRapiDrinks = [];
window.dapurRapiCategories = [];
window.dapurRapiToken = sessionStorage.getItem(authTokenKey);
window.dapurRapiUserRole = "user";

function escapeHtml(value) {
    return String(value ?? "").replace(
        /[&<>"']/g,
        (character) =>
            ({
                "&": "&amp;",
                "<": "&lt;",
                ">": "&gt;",
                '"': "&quot;",
                "'": "&#039;",
            })[character],
    );
}

function stripHtml(value) {
    const parsedValue = new DOMParser().parseFromString(
        String(value ?? ""),
        "text/html",
    );

    return parsedValue.body.textContent.trim();
}

function normalizeImageSearchText(value) {
    return String(value ?? "")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLocaleLowerCase("id-ID")
        .replace(/\.(jpe?g|png|webp|gif)$/i, "")
        .replace(/[^\p{L}\p{N}]+/gu, " ")
        .trim();
}

function findMenuImage(productName, productKind) {
    const normalizedName = normalizeImageSearchText(productName);
    const cacheKey = `${productKind}:${normalizedName}`;
    if (menuImageCache.has(cacheKey)) return menuImageCache.get(cacheKey);

    const imageRequest = (async () => {
        const categoryHint =
            productKind === "minuman" ? "Indonesian drink" : "Indonesian food";
        const parameters = new URLSearchParams({
            action: "query",
            generator: "search",
            gsrsearch: `${productName} ${categoryHint}`,
            gsrnamespace: "6",
            gsrlimit: "10",
            prop: "imageinfo",
            iiprop: "url|extmetadata",
            iiurlwidth: "720",
            format: "json",
            origin: "*",
        });

        try {
            const response = await fetch(
                `https://commons.wikimedia.org/w/api.php?${parameters}`,
            );
            if (!response.ok) return null;

            const payload = await response.json();
            const nameTerms = normalizedName
                .split(" ")
                .filter((term) => term.length > 2);
            const candidates = Object.values(payload.query?.pages ?? {})
                .map((page) => {
                    const title = normalizeImageSearchText(
                        String(page.title ?? "").replace(/^File:/i, ""),
                    );
                    const titleTerms = new Set(title.split(" "));
                    const matchingTerms = nameTerms.filter((term) =>
                        titleTerms.has(term),
                    ).length;

                    return {
                        page,
                        score:
                            nameTerms.length > 0
                                ? matchingTerms / nameTerms.length
                                : 0,
                    };
                })
                .filter((candidate) => candidate.score >= 0.5)
                .sort((first, second) => second.score - first.score);
            const page = candidates[0]?.page;
            const imageInfo = page?.imageinfo?.[0];
            if (!imageInfo?.thumburl && !imageInfo?.url) return null;

            const metadata = imageInfo.extmetadata ?? {};
            const title = String(page.title ?? "").replace(/^File:/, "");

            return {
                url: imageInfo.thumburl ?? imageInfo.url,
                pageUrl: `https://commons.wikimedia.org/wiki/${encodeURIComponent(title.replaceAll(" ", "_"))}`,
                author: stripHtml(metadata.Artist?.value),
                license: stripHtml(metadata.LicenseShortName?.value),
            };
        } catch {
            return null;
        }
    })();

    menuImageCache.set(cacheKey, imageRequest);
    return imageRequest;
}

function removeConnectedWhiteLogoBackground(image) {
    if (image.dataset.backgroundRemoved === "true") return;

    const width = image.naturalWidth;
    const height = image.naturalHeight;
    if (!width || !height) return;

    const canvas = document.createElement("canvas");
    canvas.width = width;
    canvas.height = height;
    const context = canvas.getContext("2d", { willReadFrequently: true });
    if (!context) return;

    context.drawImage(image, 0, 0);

    let pixels;
    try {
        pixels = context.getImageData(0, 0, width, height);
    } catch {
        return;
    }

    const { data } = pixels;
    const pixelCount = width * height;
    const visited = new Uint8Array(pixelCount);
    const queue = new Uint32Array(pixelCount);
    let head = 0;
    let tail = 0;

    const isEdgeWhite = (pixelIndex) => {
        const offset = pixelIndex * 4;
        const red = data[offset];
        const green = data[offset + 1];
        const blue = data[offset + 2];

        return (
            red >= 238 &&
            green >= 238 &&
            blue >= 238 &&
            Math.max(red, green, blue) - Math.min(red, green, blue) <= 18
        );
    };

    const enqueue = (pixelIndex) => {
        if (visited[pixelIndex] || !isEdgeWhite(pixelIndex)) return;

        visited[pixelIndex] = 1;
        queue[tail] = pixelIndex;
        tail += 1;
    };

    for (let x = 0; x < width; x += 1) {
        enqueue(x);
        enqueue((height - 1) * width + x);
    }
    for (let y = 1; y < height - 1; y += 1) {
        enqueue(y * width);
        enqueue(y * width + width - 1);
    }

    while (head < tail) {
        const pixelIndex = queue[head];
        head += 1;
        const x = pixelIndex % width;
        const y = Math.floor(pixelIndex / width);

        if (x > 0) enqueue(pixelIndex - 1);
        if (x < width - 1) enqueue(pixelIndex + 1);
        if (y > 0) enqueue(pixelIndex - width);
        if (y < height - 1) enqueue(pixelIndex + width);
    }

    for (let pixelIndex = 0; pixelIndex < pixelCount; pixelIndex += 1) {
        if (visited[pixelIndex]) data[pixelIndex * 4 + 3] = 0;
    }

    context.putImageData(pixels, 0, 0);
    image.src = canvas.toDataURL("image/png");
    image.dataset.backgroundRemoved = "true";
}

function initializeLoginLogo() {
    document.querySelectorAll(".login-brand-logo").forEach((image) => {
        if (image.complete) {
            removeConnectedWhiteLogoBackground(image);
        } else {
            image.addEventListener(
                "load",
                () => removeConnectedWhiteLogoBackground(image),
                { once: true },
            );
        }
    });
}

function updateRecentCarouselControls() {
    const grid = document.querySelector("[data-recent-foods]");
    const controls = document.querySelector("[data-recent-controls]");
    const slider = document.querySelector("[data-recent-slider]");

    if (!grid || !controls || !slider) return;

    const foodCount = grid.querySelectorAll(".recent-card").length;
    const pageCount = Math.ceil(foodCount / 4);
    recentCarouselPage = Math.min(
        recentCarouselPage,
        Math.max(pageCount - 1, 0),
    );
    controls.hidden = pageCount === 0;
    slider.max = String(Math.max(pageCount - 1, 0));
    slider.value = String(recentCarouselPage);
    slider.disabled = pageCount <= 1;
    slider.style.setProperty(
        "--recent-thumb-width",
        `${Math.min(100, (4 / foodCount) * 100)}%`,
    );
}

function moveRecentCarouselToPage(page) {
    const grid = document.querySelector("[data-recent-foods]");
    if (!grid) return;

    const pageCount = Math.ceil(
        grid.querySelectorAll(".recent-card").length / 4,
    );
    recentCarouselPage = Math.max(0, Math.min(page, pageCount - 1));

    const pageStartCard =
        grid.querySelectorAll(".recent-card")[recentCarouselPage * 4];
    if (pageStartCard) {
        grid.scrollTo({
            left: pageStartCard.offsetLeft - grid.offsetLeft,
            behavior: "smooth",
        });
    }

    updateRecentCarouselControls();
}

function showAuthNotice(message, type = "error") {
    const notice = document.querySelector("[data-notice]");

    if (!notice) return;

    notice.textContent = message;
    notice.classList.toggle("is-success", type === "success");
    notice.classList.toggle(
        "is-order-confirmation",
        type === "order-confirmation",
    );
    notice.hidden = false;
}

function initializeTunnelReload() {
    const environment = document.querySelector('meta[name="app-environment"]');
    if (environment?.content !== "local") return;

    let currentRevision = null;
    let reloadRequested = false;

    const checkRevision = async () => {
        if (reloadRequested || document.visibilityState !== "visible") return;

        try {
            const response = await fetch(`/__dev/revision?t=${Date.now()}`, {
                cache: "no-store",
                headers: { Accept: "application/json" },
            });
            if (!response.ok) return;

            const payload = await response.json();
            if (!currentRevision) {
                currentRevision = payload.revision;
                return;
            }

            if (payload.revision !== currentRevision) {
                reloadRequested = true;
                window.location.reload();
            }
        } catch {
            // The tunnel may briefly reconnect; retry on the next interval.
        }
    };

    checkRevision();
    window.setInterval(checkRevision, 5000);
}

async function requestApi(path, { token, ...options } = {}) {
    const isFormData = options.body instanceof FormData;
    const headers = {
        Accept: "application/json",
        ...(options.body && !isFormData
            ? { "Content-Type": "application/json" }
            : {}),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...options.headers,
    };
    const response = await fetch(`/api/${path}`, { ...options, headers });
    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        const firstError = Object.values(payload.errors ?? {}).flat()[0];
        throw new Error(
            firstError ||
                payload.message ||
                "Permintaan belum berhasil. Coba lagi.",
        );
    }

    return payload;
}

function catalogApiPath(resource, suffix = "") {
    const prefix = window.dapurRapiUserRole === "admin" ? "admin/" : "";

    return `${prefix}${resource}${suffix ? `/${suffix}` : ""}`;
}

async function optimizeRestaurantLogo(file) {
    if (file.size <= restaurantLogoMaxBytes) return file;
    if (typeof createImageBitmap !== "function") {
        throw new Error(
            "Browser tidak dapat memproses gambar. Coba unggah logo lebih kecil.",
        );
    }

    const image = await createImageBitmap(file);
    let scale = Math.min(1, 1200 / Math.max(image.width, image.height));

    try {
        for (let resizeAttempt = 0; resizeAttempt < 6; resizeAttempt++) {
            const canvas = document.createElement("canvas");
            canvas.width = Math.max(1, Math.round(image.width * scale));
            canvas.height = Math.max(1, Math.round(image.height * scale));
            const context = canvas.getContext("2d");

            if (!context) break;
            context.drawImage(image, 0, 0, canvas.width, canvas.height);

            for (const quality of [0.9, 0.8, 0.7, 0.6, 0.5]) {
                const blob = await new Promise((resolve) =>
                    canvas.toBlob(resolve, "image/webp", quality),
                );

                if (blob && blob.size <= restaurantLogoMaxBytes) {
                    const extension =
                        blob.type === "image/webp"
                            ? "webp"
                            : blob.type === "image/png"
                              ? "png"
                              : "jpg";

                    return new File([blob], `restaurant-logo.${extension}`, {
                        type: blob.type,
                        lastModified: file.lastModified,
                    });
                }
            }

            scale *= 0.8;
        }
    } finally {
        image.close();
    }

    throw new Error(
        "Logo tidak dapat diperkecil hingga ukuran yang diizinkan.",
    );
}

function initializeAuthForms() {
    const loginForm = document.querySelector('[data-auth-form="login"]');
    const registerForm = document.querySelector('[data-auth-form="register"]');
    const forgotPasswordForm = document.querySelector(
        '[data-auth-form="forgot-password"]',
    );
    const resetPasswordForm = document.querySelector(
        '[data-auth-form="reset-password"]',
    );
    const forgotPasswordButton = document.querySelector(
        "[data-forgot-password]",
    );

    if (loginForm && forgotPasswordButton) {
        const emailInput = loginForm.elements.email;
        const updateForgotPasswordAvailability = () => {
            forgotPasswordButton.disabled =
                !emailInput.value.trim() || !emailInput.validity.valid;
        };

        emailInput.addEventListener("input", updateForgotPasswordAvailability);
        updateForgotPasswordAvailability();

        forgotPasswordButton.addEventListener("click", () => {
            updateForgotPasswordAvailability();
            if (forgotPasswordButton.disabled) return;

            const query = new URLSearchParams({
                email: emailInput.value.trim(),
            });
            window.location.assign(`/forgot-password?${query.toString()}`);
        });
    }

    document
        .querySelectorAll("[data-password-visibility]")
        .forEach((toggle) => {
            toggle.addEventListener("click", () => {
                const passwordInput = document.querySelector(
                    `[data-${toggle.dataset.passwordTarget}]`,
                );
                if (!passwordInput) return;

                const isVisible = passwordInput.type === "text";
                const nextLabel = isVisible
                    ? toggle.dataset.showLabel
                    : toggle.dataset.hideLabel;

                passwordInput.type = isVisible ? "password" : "text";
                toggle.setAttribute("aria-pressed", String(!isVisible));
                toggle.setAttribute("aria-label", nextLabel);
                toggle.title = nextLabel;
                toggle
                    .querySelector("[data-password-eye-open]")
                    .toggleAttribute("hidden", isVisible);
                toggle
                    .querySelector("[data-password-eye-closed]")
                    .toggleAttribute("hidden", !isVisible);
            });
        });

    if (
        new URLSearchParams(window.location.search).has("registered") &&
        loginForm
    ) {
        showAuthNotice(
            "Akun berhasil dibuat. Silakan masuk dengan email dan kata sandimu.",
            "success",
        );
    }

    if (
        new URLSearchParams(window.location.search).has("password-reset") &&
        loginForm
    ) {
        showAuthNotice(
            "Kata sandi berhasil diatur ulang. Silakan masuk dengan kata sandi baru.",
            "success",
        );
    }

    document.querySelectorAll("[data-unavailable-link]").forEach((link) => {
        link.addEventListener("click", (event) => {
            event.preventDefault();
            showAuthNotice("Hubungi pengelola dapur untuk bantuan akses akun.");
        });
    });

    loginForm?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const button = loginForm.querySelector('button[type="submit"]');
        const formData = Object.fromEntries(new FormData(loginForm));

        button.disabled = true;
        button.querySelector("span").textContent = "Sedang masuk...";

        try {
            const payload = await requestApi("login", {
                method: "POST",
                body: JSON.stringify(formData),
            });

            sessionStorage.setItem(authTokenKey, payload.data.token);
            sessionStorage.setItem(
                authUserKey,
                JSON.stringify(payload.data.user),
            );
            window.location.assign("/dashboard");
        } catch (error) {
            showAuthNotice(error.message);
            button.disabled = false;
            button.querySelector("span").textContent = "Masuk ke dashboard";
        }
    });

    registerForm?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const button = registerForm.querySelector('button[type="submit"]');
        const formData = Object.fromEntries(new FormData(registerForm));

        if (formData.password !== formData.password_confirmation) {
            showAuthNotice("Konfirmasi kata sandi belum sama.");
            return;
        }

        button.disabled = true;
        button.querySelector("span").textContent = "Membuat akun...";

        try {
            await requestApi("register", {
                method: "POST",
                body: JSON.stringify(formData),
            });

            window.location.assign("/login?registered=1");
        } catch (error) {
            showAuthNotice(error.message);
            button.disabled = false;
            button.querySelector("span").textContent = "Buat akun";
        }
    });

    forgotPasswordForm?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const button = forgotPasswordForm.querySelector(
            'button[type="submit"]',
        );
        const buttonLabel = button.querySelector("span");
        const formData = Object.fromEntries(new FormData(forgotPasswordForm));

        button.disabled = true;
        buttonLabel.textContent = "Mengirim tautan...";

        try {
            const payload = await requestApi("forgot-password", {
                method: "POST",
                body: JSON.stringify(formData),
            });

            showAuthNotice(payload.message, "success");
            buttonLabel.textContent = "Tautan dikirim";
        } catch (error) {
            showAuthNotice(error.message);
            button.disabled = false;
            buttonLabel.textContent = "Kirim tautan reset";
        }
    });

    resetPasswordForm?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const button = resetPasswordForm.querySelector('button[type="submit"]');
        const buttonLabel = button.querySelector("span");
        const formData = Object.fromEntries(new FormData(resetPasswordForm));

        if (formData.password !== formData.password_confirmation) {
            showAuthNotice("Konfirmasi kata sandi belum sama.");
            return;
        }

        button.disabled = true;
        buttonLabel.textContent = "Menyimpan kata sandi...";

        try {
            await requestApi("reset-password", {
                method: "POST",
                body: JSON.stringify(formData),
            });

            window.location.assign("/login?password-reset=1");
        } catch (error) {
            showAuthNotice(error.message);
            button.disabled = false;
            buttonLabel.textContent = "Simpan kata sandi baru";
        }
    });
}

function unwrapData(payload) {
    if (Array.isArray(payload?.data)) return payload.data;
    if (Array.isArray(payload?.data?.data)) return payload.data.data;
    return [];
}

function collectionTotal(payload, items) {
    return Number(payload?.data?.meta?.total ?? items.length);
}

function getPendingApprovalIds() {
    try {
        const storedIds = JSON.parse(
            sessionStorage.getItem(pendingApprovalStorageKey) ?? "[]",
        );

        return Array.isArray(storedIds)
            ? storedIds.map(Number).filter(Number.isFinite)
            : [];
    } catch {
        return [];
    }
}

function savePendingApprovalIds(ids) {
    sessionStorage.setItem(pendingApprovalStorageKey, JSON.stringify(ids));
}

function addPendingApprovalId(id) {
    const pendingIds = new Set(getPendingApprovalIds());
    pendingIds.add(Number(id));
    savePendingApprovalIds([...pendingIds]);
}

function startUserApprovalPolling() {
    if (userApprovalPollingTimer || !getPendingApprovalIds().length) return;

    userApprovalPollingTimer = window.setInterval(
        pollForUserOrderApprovals,
        5000,
    );
    pollForUserOrderApprovals();
}

async function pollForUserOrderApprovals() {
    if (userApprovalPollInProgress) return;

    const pendingIds = getPendingApprovalIds();
    if (!pendingIds.length) {
        window.clearInterval(userApprovalPollingTimer);
        userApprovalPollingTimer = null;

        return;
    }

    userApprovalPollInProgress = true;

    try {
        const approvedTransactions = [];
        const cancelledTransactions = [];

        for (const transactionId of pendingIds) {
            try {
                const payload = await requestApi(
                    `transaksis/${transactionId}`,
                    {
                        token: window.dapurRapiToken,
                    },
                );
                const transaction = payload.data;

                if (["diproses", "selesai"].includes(transaction.status)) {
                    approvedTransactions.push(transaction);
                } else if (transaction.status === "dibatalkan") {
                    cancelledTransactions.push(transaction);
                }
            } catch (error) {
                if (
                    !/404|tidak ditemukan|menunggu persetujuan/i.test(
                        error.message,
                    )
                ) {
                    throw error;
                }
            }
        }

        const resolvedTransactions = [
            ...approvedTransactions,
            ...cancelledTransactions,
        ];

        if (resolvedTransactions.length) {
            const approvedIds = new Set(
                resolvedTransactions.map((transaction) =>
                    Number(transaction.id),
                ),
            );
            savePendingApprovalIds(
                pendingIds.filter((id) => !approvedIds.has(id)),
            );
            currentTransactionPage = 1;

            const transactionSearch = document.querySelector(
                "[data-transaction-search]",
            );
            if (transactionSearch) transactionSearch.value = "";

            await loadDashboardData(window.dapurRapiToken);
            if (approvedTransactions.length) {
                const approvedLabels = approvedTransactions
                    .map(
                        (transaction) =>
                            `#${String(transaction.id).padStart(4, "0")}`,
                    )
                    .join(", ");
                showDashboardNotice(
                    `Pesanan ${approvedLabels} disetujui admin dan sekarang tersedia di Transaksi.`,
                    "success",
                );
            }

            if (cancelledTransactions.length) {
                const cancelledLabels = cancelledTransactions
                    .map(
                        (transaction) =>
                            `#${String(transaction.id).padStart(4, "0")}`,
                    )
                    .join(", ");
                showDashboardNotice(
                    `Pesanan ${cancelledLabels} dibatalkan oleh pengelola.`,
                );
            }
        }
    } catch {
        // Retry on the next poll if the dashboard is temporarily offline.
    } finally {
        userApprovalPollInProgress = false;
    }
}

function formatCurrency(amount) {
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(amount) || 0);
}

function formatDate(value) {
    if (!value) return "—";

    return new Intl.DateTimeFormat("id-ID", {
        day: "numeric",
        month: "short",
        year: "numeric",
    }).format(new Date(value));
}

function renderReviewFoodOptions(foods, drinks) {
    const select = document.querySelector("[data-review-food]");
    if (!select) return;

    const currentValue = select.value;
    const menuCount = foods.length + drinks.length;
    const placeholder = menuCount
        ? "Pilih makanan atau minuman yang sudah dicoba"
        : "Belum ada menu yang tersedia";
    select.innerHTML = [
        `<option value="">${placeholder}</option>`,
        ...(foods.length
            ? [
                  '<optgroup label="Makanan">',
                  ...foods.map(
                      (food) =>
                          `<option value="makanan:${Number(food.id)}">${escapeHtml(food.nama_makanan)}</option>`,
                  ),
                  "</optgroup>",
              ]
            : []),
        ...(drinks.length
            ? [
                  '<optgroup label="Minuman">',
                  ...drinks.map(
                      (drink) =>
                          `<option value="minuman:${Number(drink.id)}">${escapeHtml(drink.nama_minuman)}</option>`,
                  ),
                  "</optgroup>",
              ]
            : []),
    ].join("");
    select.value = [...foods, ...drinks].some((item) =>
        currentValue.endsWith(`:${item.id}`),
    )
        ? currentValue
        : "";
    select.disabled = menuCount === 0;
    updateReviewSubmitState();
}

function updateReviewSubmitState() {
    const foodSelect = document.querySelector("[data-review-food]");
    const ratingInput = document.querySelector("[data-review-rating]");
    const submitButton = document.querySelector(
        '[data-review-form] button[type="submit"]',
    );

    if (!foodSelect || !ratingInput || !submitButton) return;

    submitButton.disabled =
        !foodSelect.value || ratingInput.dataset.ratingTouched !== "true";
}

function renderAdminReviews(reviews) {
    const body = document.querySelector("[data-admin-review-rows]");
    if (!body) return;

    const count = document.querySelector("[data-admin-review-count]");
    const average = document.querySelector("[data-admin-review-average]");
    count.textContent = reviews.length.toLocaleString("id-ID");
    document.querySelector('[data-metric="reviews"]').textContent =
        reviews.length.toLocaleString("id-ID");
    const ratingAverage = reviews.length
        ? reviews.reduce((total, review) => total + Number(review.rating), 0) /
          reviews.length
        : null;
    average.textContent = ratingAverage
        ? ratingAverage.toLocaleString("id-ID", {
              minimumFractionDigits: 1,
              maximumFractionDigits: 1,
          })
        : "—";

    if (reviews.length === 0) {
        body.innerHTML =
            '<tr><td class="table-empty" colspan="7">Belum ada ulasan pelanggan.</td></tr>';
        return;
    }

    body.innerHTML = reviews
        .map((review) => {
            const reviewId = Number(review.id);
            const hasReply = Boolean(review.balasan_admin?.trim());
            const replyMarkup = hasReply
                ? `<div class="admin-review-reply-sent"><span class="admin-review-reply-mark" aria-hidden="true">✓</span><p>${escapeHtml(review.balasan_admin)}</p></div>`
                : `<form class="admin-review-reply-form" data-review-reply-form="${reviewId}"><textarea class="text-field text-area" name="balasan_admin" rows="2" maxlength="2000" aria-label="Balasan untuk ulasan ${reviewId}" placeholder="Tulis balasan..." required></textarea></form>`;
            const actionMarkup = hasReply
                ? '<span class="admin-review-replied-status"><span aria-hidden="true">✓</span> Terkirim</span>'
                : `<button class="table-action" type="submit" form="review-reply-${reviewId}" data-review-reply-submit="${reviewId}">Balas</button>`;

            return `
                <tr class="admin-review-row${hasReply ? " is-replied" : ""}">
                    <td><span class="admin-review-id">#${reviewId}</span></td>
                    <td><strong class="admin-review-customer">${escapeHtml(review.nama_pengulas || "Pelanggan")}</strong></td>
                    <td><span class="review-menu-kind">${escapeHtml(review.jenis_menu || "Makanan")}</span><span class="admin-review-menu-name">${escapeHtml(review.nama_menu || "Menu")}</span></td>
                    <td><span class="review-entry-score">${Number(review.rating).toLocaleString("id-ID", { maximumFractionDigits: 1 })} / 10</span></td>
                    <td class="admin-review-comment">${escapeHtml(review.komentar || "-")}</td>
                    <td class="admin-review-reply-cell">${replyMarkup}</td>
                    <td class="admin-review-action-cell">${actionMarkup}</td>
                </tr>
            `;
        })
        .join("");

    body.querySelectorAll("[data-review-reply-form]").forEach((form) => {
        form.id = `review-reply-${form.dataset.reviewReplyForm}`;
    });
}

function updateCustomerReviewMetric(reviewCount) {
    customerReviewCount = Number(reviewCount) || 0;
    const metric = document.querySelector('[data-metric="my-reviews"]');
    if (metric)
        metric.textContent = customerReviewCount.toLocaleString("id-ID");
}

async function loadAdminReviews(token) {
    if (window.dapurRapiUserRole !== "admin") return;

    const body = document.querySelector("[data-admin-review-rows]");
    if (!body) return;

    try {
        const payload = await requestApi("admin/ulasans", { token });
        renderAdminReviews(unwrapData(payload));
    } catch {
        setTableMessage(
            "[data-admin-review-rows]",
            "Data ulasan belum dapat dimuat.",
            7,
        );
    }
}

function formatDateTime(value) {
    if (!value) return "Belum pernah login";

    return new Intl.DateTimeFormat("id-ID", {
        day: "numeric",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    }).format(new Date(value));
}

function setTableMessage(selector, message, columns) {
    const body = document.querySelector(selector);
    if (body)
        body.innerHTML = `<tr><td class="table-empty" colspan="${columns}">${escapeHtml(message)}</td></tr>`;
}

function showDashboardNotice(message, type = "error") {
    const notice = document.querySelector("[data-dashboard-notice]");
    if (!notice) return;

    notice.textContent = message;
    notice.classList.toggle("is-success", type === "success");
    notice.hidden = false;
}

function initializeNavigation() {
    const shell = document.querySelector("[data-dashboard]");
    if (!shell) return;
    const menuToggle = document.querySelector("[data-menu-toggle]");
    const setMenuOpen = (isOpen) => {
        shell.classList.toggle("menu-open", isOpen);
        menuToggle?.setAttribute("aria-expanded", String(isOpen));
        menuToggle?.setAttribute(
            "aria-label",
            isOpen ? "Tutup menu" : "Buka menu",
        );
    };

    const navigate = (page) => {
        document.querySelectorAll("[data-panel]").forEach((panel) => {
            panel.classList.toggle("is-visible", panel.dataset.panel === page);
        });
        document.querySelectorAll(".nav-item[data-page]").forEach((item) => {
            item.classList.toggle("is-active", item.dataset.page === page);
        });

        const activeItem = document.querySelector(
            `.nav-item[data-page="${page}"]`,
        );
        const breadcrumb = document.querySelector("[data-breadcrumb]");
        if (breadcrumb && activeItem)
            breadcrumb.textContent =
                activeItem.querySelector("span:nth-child(2)").textContent;
        setMenuOpen(false);
    };

    document
        .querySelectorAll("[data-page], [data-switch-page]")
        .forEach((control) => {
            control.addEventListener("click", () =>
                navigate(control.dataset.page || control.dataset.switchPage),
            );
        });

    menuToggle?.addEventListener("click", () => {
        setMenuOpen(!shell.classList.contains("menu-open"));
    });
    document
        .querySelector("[data-menu-backdrop]")
        ?.addEventListener("click", () => setMenuOpen(false));

    let searchTimeout;
    document
        .querySelector("[data-food-search]")
        ?.addEventListener("input", (event) => {
            const query = event.currentTarget.value.trim();
            window.clearTimeout(searchTimeout);
            searchTimeout = window.setTimeout(async () => {
                try {
                    const payload = await requestApi(
                        `${catalogApiPath("makanans")}?search=${encodeURIComponent(query)}`,
                        {
                            token: window.dapurRapiToken,
                        },
                    );
                    const foods = unwrapData(payload);
                    window.dapurRapiFoods = foods;
                    renderFoodsTable(foods, collectionTotal(payload, foods));
                } catch (error) {
                    showDashboardNotice(
                        error.message || "Pencarian belum dapat dilakukan.",
                    );
                }
            }, 250);
        });

    document
        .querySelector("[data-logout]")
        ?.addEventListener("click", async () => {
            const token = sessionStorage.getItem(authTokenKey);

            try {
                await requestApi("logout", { method: "POST", token });
            } catch {
                // Clear the local session even if the server has already expired the token.
            }

            sessionStorage.removeItem(authTokenKey);
            sessionStorage.removeItem(authUserKey);
            sessionStorage.removeItem(pendingApprovalStorageKey);
            window.location.assign("/login");
        });
}

function renderFoodsTable(foods, total = foods.length) {
    const body = document.querySelector("[data-food-rows]");
    if (!body) return;

    const heading = document.querySelector("[data-foods-heading]");
    if (heading) heading.textContent = total.toLocaleString("id-ID");
    if (foods.length === 0) {
        setTableMessage(
            "[data-food-rows]",
            "Belum ada makanan yang cocok dengan pencarian ini.",
            6,
        );
        return;
    }

    const isAdmin = window.dapurRapiUserRole === "admin";
    body.innerHTML = foods
        .map((food) => {
            const actions = isAdmin
                ? `<div class="row-actions"><button class="table-action" type="button" data-food-edit="${Number(food.id)}">Ubah</button><button class="table-action table-action-danger" type="button" data-food-delete="${Number(food.id)}">Hapus</button></div>`
                : `<button class="table-action table-action-buy" type="button" data-food-buy="${Number(food.id)}" ${Number(food.stok) < 1 ? "disabled" : ""}>${Number(food.stok) < 1 ? "Habis" : "Beli"}</button>`;

            return `
        <tr class="mobile-data-card">
            <td class="mobile-card-title">${escapeHtml(food.nama_makanan)}</td>
            <td data-label="Kategori">${escapeHtml(food.kategori?.nama ?? food.nama_kategori ?? "Belum dikategorikan")}</td>
            <td data-label="Deskripsi" class="mobile-card-description">${escapeHtml(food.deskripsi || "—")}</td>
            <td data-label="Harga" class="mobile-card-price">${escapeHtml(formatCurrency(food.harga))}</td>
            <td data-label="Stok">${escapeHtml(food.stok ?? 0)} unit</td>
            <td data-label="Aksi" class="mobile-card-actions">${actions}</td>
		</tr>
	`;
        })
        .join("");
}

function renderMinumansTable(drinks, total = drinks.length) {
    const body = document.querySelector("[data-drink-rows]");
    if (!body) return;

    const heading = document.querySelector("[data-drinks-heading]");
    if (heading) heading.textContent = total.toLocaleString("id-ID");

    if (drinks.length === 0) {
        setTableMessage(
            "[data-drink-rows]",
            "Belum ada minuman yang cocok dengan pencarian ini.",
            6,
        );
        return;
    }

    const isAdmin = window.dapurRapiUserRole === "admin";
    body.innerHTML = drinks
        .map((drink) => {
            const actions = isAdmin
                ? `<div class="row-actions"><button class="table-action" type="button" data-drink-edit="${Number(drink.id)}">Ubah</button><button class="table-action table-action-danger" type="button" data-drink-delete="${Number(drink.id)}">Hapus</button></div>`
                : `<button class="table-action table-action-buy" type="button" data-drink-buy="${Number(drink.id)}" ${Number(drink.stok) < 1 ? "disabled" : ""}>${Number(drink.stok) < 1 ? "Habis" : "Beli"}</button>`;

            return `<tr class="mobile-data-card"><td class="mobile-card-title">${escapeHtml(drink.nama_minuman)}</td><td data-label="Kategori">${escapeHtml(drink.kategori?.nama_kategori ?? "Belum dikategorikan")}</td><td data-label="Deskripsi" class="mobile-card-description">${escapeHtml(drink.deskripsi || "—")}</td><td data-label="Harga" class="mobile-card-price">${escapeHtml(formatCurrency(drink.harga))}</td><td data-label="Stok">${escapeHtml(drink.stok)} unit</td><td data-label="Aksi" class="mobile-card-actions">${actions}</td></tr>`;
        })
        .join("");
}

function renderOverview(
    foods,
    drinks,
    categories,
    transactions,
    foodTotal,
    drinkTotal,
    transactionTotal,
    topSellingFood,
) {
    const threshold = Number(
        document.querySelector("[data-low-stock-alert]")?.dataset
            .lowStockThreshold ?? 10,
    );
    const lowStockProducts = [
        ...foods
            .filter((food) => Number(food.stok) <= threshold)
            .map((food) => ({
                name: food.nama_makanan,
                category:
                    food.kategori?.nama ??
                    food.nama_kategori ??
                    "Belum dikategorikan",
                price: food.harga,
                stock: food.stok,
            })),
        ...drinks
            .filter((drink) => Number(drink.stok) <= threshold)
            .map((drink) => ({
                name: drink.nama_minuman,
                category: "Minuman",
                price: drink.harga,
                stock: drink.stok,
            })),
    ].sort((first, second) => Number(first.stock) - Number(second.stock));
    const isAdmin = window.dapurRapiUserRole === "admin";
    const metrics = {
        foods: foodTotal,
        drinks: drinkTotal,
        categories: categories.length,
        "low-stock": isAdmin
            ? lowStockProducts.length
            : Number(topSellingFood?.quantity_sold ?? 0),
        transactions: transactionTotal,
    };

    Object.entries(metrics).forEach(([key, value]) => {
        const node = document.querySelector(`[data-metric="${key}"]`);
        if (node) node.textContent = value.toLocaleString("id-ID");
    });
    document.querySelector('[data-metric-label="low-stock"]').textContent =
        isAdmin ? "Stok menipis" : "Menu terlaris";
    document.querySelector('[data-metric-icon="low-stock"]').innerHTML = isAdmin
        ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 2.8 19a1.4 1.4 0 0 0 1.2 2h16a1.4 1.4 0 0 0 1.2-2L12 3Z"/><path d="M12 9v5m0 3h.01"/></svg>'
        : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3 2.7 5.5 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1-4.4-4.3 6.1-.9L12 3Z"/></svg>';
    document.querySelector('[data-metric-note="low-stock"]').textContent =
        isAdmin
            ? `makanan dan minuman yang perlu dicek`
            : (topSellingFood?.nama_makanan ?? "Belum ada pesanan");

    const lowStockAlert = document.querySelector("[data-low-stock-alert]");
    lowStockAlert.hidden = !isAdmin || lowStockProducts.length === 0;
    if (isAdmin && lowStockProducts.length > 0) {
        document.querySelector("[data-low-stock-alert-text]").textContent =
            `${lowStockProducts.length} makanan/minuman memiliki stok ${threshold} atau kurang.`;
    }

    const lowStockBody = document.querySelector("[data-low-stock-rows]");
    if (lowStockBody) {
        if (lowStockProducts.length === 0) {
            setTableMessage(
                "[data-low-stock-rows]",
                "Semua stok terlihat aman. Tidak ada yang perlu direstok.",
                5,
            );
        } else {
            lowStockBody.innerHTML = lowStockProducts
                .slice(0, 5)
                .map(
                    (product) => `
				<tr>
                    <td>${escapeHtml(product.name)}</td>
                    <td>${escapeHtml(product.category)}</td>
                    <td>${escapeHtml(formatCurrency(product.price))}</td>
                    <td>${escapeHtml(product.stock ?? 0)} unit</td>
					<td><span class="status-pill">Perlu restok</span></td>
				</tr>
			`,
                )
                .join("");
        }
    }

    const recentProducts = [
        ...foods.map((food) => ({
            id: food.id,
            name: food.nama_makanan,
            kind: "makanan",
            category: food.kategori?.nama ?? food.nama_kategori ?? "Menu dapur",
        })),
        ...drinks.map((drink) => ({
            id: drink.id,
            name: drink.nama_minuman,
            kind: "minuman",
            category: drink.kategori?.nama_kategori ?? "Minuman",
        })),
    ].reverse();
    const recentContainer = document.querySelector("[data-recent-foods]");
    if (recentContainer) {
        recentCarouselPage = 0;
        recentContainer.scrollLeft = 0;
        recentContainer.innerHTML = recentProducts.length
            ? recentProducts
                  .map(
                      (product) => `
			<article class="recent-card">
                <div class="recent-photo-wrap"><img class="recent-photo" data-product-id="${escapeHtml(product.id)}" data-product-kind="${product.kind}" src="${menuFallbackImages[product.kind]}" alt="Foto ${escapeHtml(product.name)}"><a class="recent-photo-credit" data-menu-photo-credit hidden target="_blank" rel="noopener noreferrer">i</a></div>
                <div class="recent-copy"><strong>${escapeHtml(product.name)}</strong><span>${product.kind === "minuman" ? escapeHtml(product.category) : `Makanan · ${escapeHtml(product.category)}`}</span></div>
			</article>
		`,
                  )
                  .join("")
            : '<div class="empty-inline">Belum ada makanan atau minuman yang terdaftar.</div>';

        recentContainer
            .querySelectorAll("[data-product-id]")
            .forEach((image) => {
                const productName = image.alt.replace(/^Foto /, "");
                const fallbackImage =
                    menuFallbackImages[image.dataset.productKind];
                image.addEventListener(
                    "error",
                    () => {
                        image.src = fallbackImage;
                    },
                    { once: true },
                );

                findMenuImage(productName, image.dataset.productKind).then(
                    (imageDetails) => {
                        if (!imageDetails || !image.isConnected) return;

                        image.src = imageDetails.url;
                        const credit = image.parentElement.querySelector(
                            "[data-menu-photo-credit]",
                        );
                        credit.href = imageDetails.pageUrl;
                        const creditDetails = [
                            imageDetails.author,
                            imageDetails.license,
                        ].filter(Boolean);
                        credit.title = creditDetails.length
                            ? `Sumber foto: ${creditDetails.join(", ")}`
                            : "Sumber foto di Wikimedia Commons";
                        credit.setAttribute("aria-label", credit.title);
                        credit.hidden = false;
                    },
                );
            });
        updateRecentCarouselControls();
    }
}

function renderCategories(categories, foods, drinks) {
    const grid = document.querySelector("[data-category-grid]");
    if (!grid) return;

    if (categories.length === 0) {
        grid.innerHTML =
            '<div class="empty-inline">Belum ada kategori yang terdaftar.</div>';
        return;
    }

    const isAdmin = window.dapurRapiUserRole === "admin";
    grid.innerHTML = categories
        .map((category) => {
            const foodCount = foods.filter(
                (food) =>
                    Number(food.kategori?.id ?? food.kategori_id) ===
                        Number(category.id) ||
                    food.nama_kategori === category.nama_kategori ||
                    food.kategori?.nama === category.nama_kategori,
            ).length;
            const drinkCount = drinks.filter(
                (drink) =>
                    Number(drink.kategori?.id ?? drink.kategori_id) ===
                    Number(category.id),
            ).length;
            const count = foodCount + drinkCount;
            const initials = category.nama_kategori
                .trim()
                .slice(0, 1)
                .toLocaleUpperCase("id-ID");

            const actions = isAdmin
                ? `<div class="category-actions"><button class="icon-button" type="button" data-category-edit="${Number(category.id)}" aria-label="Ubah ${escapeHtml(category.nama_kategori)}" title="Ubah kategori">✎</button><button class="icon-button" type="button" data-category-delete="${Number(category.id)}" aria-label="Hapus ${escapeHtml(category.nama_kategori)}" title="Hapus kategori">×</button></div>`
                : "";

            return `<article class="category-item"><span class="category-stamp" aria-hidden="true">${escapeHtml(initials)}</span><span class="category-copy"><strong>${escapeHtml(category.nama_kategori)}</strong><small>${count} menu pada halaman ini</small></span>${actions}</article>`;
        })
        .join("");
}

function renderTransactions(transactions, pagination = {}) {
    const body = document.querySelector("[data-transaction-rows]");
    if (!body) return;
    body.closest("table")?.classList.add(
        "mobile-card-table",
        "transaction-data-table",
    );

    currentTransactionPage = Number(pagination.current_page ?? 1);
    const lastPage = Number(pagination.last_page ?? 1);
    const paginationControls = document.querySelector(
        "[data-transaction-pagination]",
    );

    if (paginationControls) {
        paginationControls.hidden = lastPage <= 1;
        paginationControls.querySelector(
            "[data-transaction-previous]",
        ).disabled = currentTransactionPage <= 1;
        paginationControls.querySelector("[data-transaction-next]").disabled =
            currentTransactionPage >= lastPage;
        paginationControls.querySelector(
            "[data-transaction-page-label]",
        ).textContent =
            `Halaman ${currentTransactionPage} dari ${lastPage} · ${Number(pagination.total ?? transactions.length).toLocaleString("id-ID")} transaksi`;
    }

    const isAdmin = window.dapurRapiUserRole === "admin";
    const columns = isAdmin ? 7 : 6;
    const headers = document.querySelector("[data-transaction-head]");

    if (headers) {
        headers.innerHTML = isAdmin
            ? "<th>Nomor</th><th>Pemesan</th><th>Makanan</th><th>Tanggal</th><th>Total</th><th>Status pesanan</th><th>Pembayaran</th>"
            : "<th>Nomor</th><th>Makanan</th><th>Tanggal</th><th>Total</th><th>Status pesanan</th><th>Pembayaran</th>";
    }

    if (transactions.length === 0) {
        setTableMessage(
            "[data-transaction-rows]",
            isAdmin
                ? "Belum ada transaksi dari pengguna."
                : "Belum ada transaksi pada akun ini.",
            columns,
        );
        filterTransactions();
        return;
    }

    const statusOptions = [
        ["pending", "Menunggu"],
        ["diproses", "Diproses"],
        ["selesai", "Selesai"],
        ["dibatalkan", "Dibatalkan"],
    ];

    const visibleTransactions = isAdmin
        ? transactions
        : [...transactions].reverse();

    body.innerHTML = visibleTransactions
        .map((transaction) => {
            const status = String(transaction.status ?? "menunggu");
            const statusClass = ["selesai", "completed", "success"].includes(
                status.toLowerCase(),
            )
                ? "is-ok"
                : "is-pending";
            const statusLabel =
                statusOptions.find(([value]) => value === status)?.[1] ??
                status;
            const payment = transaction.payments?.at(-1);
            const paymentMethods = {
                bank_transfer: "Transfer bank",
                qris: "QRIS",
                cash_on_pickup: "Tunai saat ambil",
            };
            const paymentStatuses = {
                pending: "Menunggu pembayaran",
                processing: "Memeriksa pembayaran",
                paid: "Lunas",
                failed: "Gagal",
                expired: "Kedaluwarsa",
                refunded: "Dikembalikan",
            };
            const paymentStatusOptions = Object.entries(paymentStatuses);
            const isRefunded = payment?.status === "refunded";
            const isCompletedOrPaid =
                status === "selesai" || payment?.status === "paid";
            const isLocked = isRefunded || isCompletedOrPaid;
            const foodItems = transaction.items?.length
                ? `<ul class="transaction-food-list">${transaction.items
                      .map(
                          (item) =>
                              `<li><span>${escapeHtml(item.nama_makanan ?? "Menu dihapus")}</span><small>× ${escapeHtml(item.qty)}</small></li>`,
                      )
                      .join("")}</ul>`
                : '<span class="transaction-food-empty">Rincian tidak tersedia</span>';
            const statusDisplay =
                isAdmin && status === "pending"
                    ? `<button class="table-action transaction-approve-button" type="button" data-approve-transaction="${Number(transaction.id)}" aria-label="Setujui transaksi ${escapeHtml(String(transaction.id))}">Oke</button>`
                    : isAdmin
                      ? `<select class="text-field transaction-status" data-transaction-status="${Number(transaction.id)}" data-current-status="${escapeHtml(status)}" aria-label="Ubah status transaksi ${escapeHtml(String(transaction.id))}" ${isLocked ? `disabled title="${isRefunded ? "Transaksi sudah dikembalikan" : "Transaksi sudah selesai/lunas"}"` : ""}>${statusOptions
                            .map(
                                ([value, label]) =>
                                    `<option value="${value}" ${value === status ? "selected" : ""}>${label}</option>`,
                            )
                            .join("")}</select>`
                      : `<span class="status-pill ${statusClass}">${escapeHtml(statusLabel)}</span>`;
            const paymentDisplay = payment
                ? `<span class="payment-method-label">${escapeHtml(paymentMethods[payment.payment_method] ?? payment.payment_method)}</span>${
                      isAdmin
                          ? `<select class="text-field payment-status-select" data-transaction-payment-status="${Number(transaction.id)}" data-current-status="${escapeHtml(payment.status)}" aria-label="Ubah status pembayaran transaksi ${escapeHtml(String(transaction.id))}" ${isLocked ? `disabled title="${isRefunded ? "Pembayaran sudah dikembalikan" : "Transaksi sudah selesai/lunas"}"` : ""}>${paymentStatusOptions
                                .map(
                                    ([value, label]) =>
                                        `<option value="${value}" ${value === payment.status ? "selected" : ""}>${escapeHtml(label)}</option>`,
                                )
                                .join("")}</select>`
                          : `<small class="payment-status-label ${["failed", "expired"].includes(payment.status) ? "is-failed" : ""}">${escapeHtml(paymentStatuses[payment.status] ?? payment.status)}</small>`
                  }`
                : '<span class="payment-status-label">Belum ada data</span>';

            return `
            <tr class="mobile-data-card ${isAdmin && isRefunded ? "transaction-row-refunded" : isAdmin && isCompletedOrPaid ? "transaction-row-completed" : ""}" data-transaction-row-status="${escapeHtml(status)}" data-transaction-row-payment-status="${escapeHtml(payment?.status ?? "")}" ${isAdmin && isLocked ? 'aria-disabled="true"' : ""}>
                <td class="mobile-card-title"><span>#${escapeHtml(String(transaction.id).padStart(4, "0"))}</span></td>
                ${isAdmin ? `<td data-label="Pelanggan">${escapeHtml(transaction.user?.name ?? "Pengguna")}</td>` : ""}
                <td data-label="Menu" class="mobile-card-description">${foodItems}</td>
				<td data-label="Tanggal">${escapeHtml(formatDate(transaction.created_at))}</td>
				<td data-label="Total" class="mobile-card-price">${escapeHtml(formatCurrency(transaction.total_harga))}</td>
				<td data-label="Status pesanan">${statusDisplay}</td>
                <td data-label="Pembayaran">${paymentDisplay}</td>
			</tr>
		`;
        })
        .join("");

    filterTransactions();
}

function transactionRequestPath(page) {
    const params = new URLSearchParams({ page: String(page) });
    const search = document
        .querySelector("[data-transaction-search]")
        ?.value.trim();

    if (search) params.set("search", search);

    const status = document.querySelector(
        "[data-transaction-status-filter]",
    )?.value;
    const paymentStatus = document.querySelector(
        "[data-transaction-payment-filter]",
    )?.value;

    if (status) params.set("status", status);
    if (paymentStatus) params.set("payment_status", paymentStatus);

    const prefix = window.dapurRapiUserRole === "admin" ? "admin/" : "";

    return `${prefix}transaksis?${params.toString()}`;
}

async function loadTransactionPage(page) {
    const previousButton = document.querySelector(
        "[data-transaction-previous]",
    );
    const nextButton = document.querySelector("[data-transaction-next]");
    previousButton.disabled = true;
    nextButton.disabled = true;

    try {
        const payload = await requestApi(transactionRequestPath(page), {
            token: window.dapurRapiToken,
        });
        renderTransactions(unwrapData(payload), payload.pagination);

        const activeCount = document.querySelector(
            '[data-metric="transactions"]',
        );
        if (activeCount && payload.active_transactions_count !== undefined) {
            activeCount.textContent = Number(
                payload.active_transactions_count,
            ).toLocaleString("id-ID");
        }
    } catch (error) {
        previousButton.disabled = currentTransactionPage <= 1;
        nextButton.disabled = false;
        showDashboardNotice(error.message);
    }
}

function updateAdminOrderNotificationVisibility() {
    const notifications = document.querySelector(
        "[data-admin-order-notifications]",
    );
    if (notifications) {
        notifications.hidden = notifications.childElementCount === 0;
    }
}

function showNewOrderNotification(transaction) {
    const notifications = document.querySelector(
        "[data-admin-order-notifications]",
    );
    if (!notifications) return;

    const transactionId = Number(transaction.id);
    if (
        notifications.querySelector(
            `[data-admin-order-notice="${transactionId}"]`,
        )
    ) {
        return;
    }

    const foodSummary = (transaction.items ?? [])
        .map((item) => `${item.nama_makanan ?? "Menu"} × ${item.qty}`)
        .join(", ");
    const notice = document.createElement("article");
    notice.className = "admin-order-notice";
    notice.dataset.adminOrderNotice = String(transactionId);
    notice.innerHTML = `
        <div class="admin-order-notice-heading">
            <div class="admin-order-notice-copy">
                <strong>Pesanan baru #${escapeHtml(String(transactionId).padStart(4, "0"))}</strong>
                <span>${escapeHtml(transaction.user?.name ?? "Pengguna")} · ${escapeHtml(formatCurrency(transaction.total_harga))}</span>
            </div>
            <button class="admin-order-notice-dismiss" type="button" data-dismiss-order-notice aria-label="Tutup pemberitahuan">×</button>
        </div>
        <p class="admin-order-notice-items">${escapeHtml(foodSummary || "Rincian makanan tidak tersedia")}</p>
        <div class="admin-order-notice-actions"><button class="table-action transaction-approve-button" type="button" data-approve-transaction="${transactionId}">Oke</button></div>
    `;
    notifications.append(notice);
    notifications.hidden = false;
}

async function pollForNewAdminOrders() {
    try {
        const payload = await requestApi(
            `${window.dapurRapiUserRole === "admin" ? "admin/" : ""}transaksis?page=1`,
            {
                token: window.dapurRapiToken,
            },
        );
        const transactions = unwrapData(payload);
        const newTransactions = transactions
            .filter(
                (transaction) =>
                    Number(transaction.id) > latestKnownAdminTransactionId,
            )
            .sort((first, second) => Number(first.id) - Number(second.id));

        for (const transaction of newTransactions) {
            if (transaction.status === "pending") {
                showNewOrderNotification(transaction);
            }

            latestKnownAdminTransactionId = Math.max(
                latestKnownAdminTransactionId,
                Number(transaction.id),
            );
        }
    } catch {
        // Leave the dashboard available when a background check briefly fails.
    }
}

async function approvePendingTransaction(transactionId, button) {
    button.disabled = true;

    try {
        await requestApi(`admin/transaksis/${transactionId}/status`, {
            method: "PUT",
            token: window.dapurRapiToken,
            body: JSON.stringify({ status: "diproses" }),
        });
        document
            .querySelector(`[data-admin-order-notice="${transactionId}"]`)
            ?.remove();
        updateAdminOrderNotificationVisibility();
        showDashboardNotice(
            "Pesanan disetujui dan sedang diproses.",
            "success",
        );
        await loadDashboardData(window.dapurRapiToken);
    } catch (error) {
        button.disabled = false;
        showDashboardNotice(error.message);
    }
}

function filterTransactions() {
    const body = document.querySelector("[data-transaction-rows]");
    const tools = document.querySelector("[data-admin-transaction-tools]");
    if (!body || !tools || tools.hidden) return;

    const query = document
        .querySelector("[data-transaction-search]")
        .value.trim()
        .toLocaleLowerCase("id-ID");

    const selectedStatus = document.querySelector(
        "[data-transaction-status-filter]",
    ).value;
    const rows = [...body.querySelectorAll("tr")].filter(
        (row) => !row.querySelector(".table-empty, .table-loading"),
    );
    let visibleCount = 0;

    rows.forEach((row) => {
        const status = row.dataset.transactionRowStatus;
        const paymentStatus = row.dataset.transactionRowPaymentStatus;
        const rowText = [...row.cells]
            .map((cell) => {
                const select = cell.querySelector("select");
                return (
                    select?.selectedOptions[0]?.textContent ?? cell.textContent
                );
            })
            .join(" ");
        const matchesQuery = rowText.toLocaleLowerCase("id-ID").includes(query);
        const matchesStatus = !selectedStatus || status === selectedStatus;
        const selectedPaymentStatus = document.querySelector(
            "[data-transaction-payment-filter]",
        ).value;
        const matchesPaymentStatus =
            !selectedPaymentStatus || paymentStatus === selectedPaymentStatus;
        row.hidden = !(matchesQuery && matchesStatus && matchesPaymentStatus);
        if (!row.hidden) visibleCount += 1;
    });

    document.querySelector("[data-transaction-result-count]").textContent =
        `${visibleCount.toLocaleString("id-ID")} dari ${rows.length.toLocaleString("id-ID")} pesanan`;
}

function renderLoginActivity(users) {
    const body = document.querySelector("[data-login-activity-rows]");
    if (!body) return;

    if (users.length === 0) {
        setTableMessage(
            "[data-login-activity-rows]",
            "Belum ada akun pengguna.",
            4,
        );
        return;
    }

    body.innerHTML = users
        .map(
            (user) => `
                <tr>
                    <td>${escapeHtml(user.name)}</td>
                    <td>${escapeHtml(user.email)}</td>
                    <td>${escapeHtml(user.role === "admin" ? "Admin" : "Pengguna")}</td>
                    <td>${escapeHtml(formatDateTime(user.last_login_at))}</td>
                </tr>
            `,
        )
        .join("");
}

function renderProfile(profile) {
    const nameParts = profile.name.trim().split(/\s+/);
    const hour = new Date().getHours();
    const greeting =
        hour >= 5 && hour < 11
            ? "Selamat pagi"
            : hour >= 11 && hour < 15
              ? "Selamat siang"
              : hour >= 15 && hour < 18
                ? "Selamat sore"
                : "Selamat malam";

    document.querySelector("[data-user-name]").textContent = profile.name;
    document.querySelector("[data-user-email]").textContent = profile.email;
    document.querySelector("[data-greeting-label]").textContent = greeting;
    document.querySelector("[data-greeting-name]").textContent = nameParts[0];
    document.querySelector("[data-avatar]").textContent = nameParts
        .slice(0, 2)
        .map((part) => part[0])
        .join("")
        .toLocaleUpperCase("id-ID");
}

function renderRestaurantProfile(profile) {
    window.dapurRapiRestaurantProfile = profile;

    document.querySelectorAll("[data-restaurant-name]").forEach((element) => {
        element.textContent = profile.name;
    });

    document.querySelectorAll("[data-brand-logo]").forEach((image) => {
        image.hidden = !profile.logo_url;
        image.src = profile.logo_url ?? "";
        image.previousElementSibling.hidden = Boolean(profile.logo_url);
    });

    const viewName = document.querySelector("[data-restaurant-view-name]");
    const viewHistory = document.querySelector(
        "[data-restaurant-view-history]",
    );
    const viewLogo = document.querySelector("[data-restaurant-view-logo]");
    const viewLogoMark = document.querySelector("[data-profile-logo-mark]");
    const editorLogo = document.querySelector("[data-restaurant-editor-logo]");
    const editorLogoMark = document.querySelector("[data-editor-logo-mark]");

    if (viewName) viewName.textContent = profile.name;
    if (viewHistory) {
        viewHistory.textContent =
            profile.history?.trim() || "Sejarah restoran belum ditambahkan.";
    }
    if (viewLogo) {
        viewLogo.hidden = !profile.logo_url;
        viewLogo.src = profile.logo_url ?? "";
        viewLogoMark.hidden = Boolean(profile.logo_url);
    }
    if (editorLogo) {
        editorLogo.hidden = !profile.logo_url;
        editorLogo.src = profile.logo_url ?? "";
        editorLogoMark.hidden = Boolean(profile.logo_url);
    }
}

function openRestaurantDialog() {
    const dialog = document.querySelector("[data-restaurant-dialog]");
    const viewer = document.querySelector("[data-restaurant-profile-view]");
    const form = document.querySelector("[data-restaurant-form]");
    const isAdmin = window.dapurRapiUserRole === "admin";

    viewer.hidden = isAdmin;
    form.hidden = !isAdmin;

    if (isAdmin) {
        form.reset();
        document.querySelector("[data-restaurant-name-input]").value =
            window.dapurRapiRestaurantProfile.name;
        document.querySelector("[data-restaurant-history-input]").value =
            window.dapurRapiRestaurantProfile.history ?? "";
        renderRestaurantProfile(window.dapurRapiRestaurantProfile);
    }

    dialog.showModal();
}

async function loadDashboardData(token) {
    const profilePayload = await requestApi("me", { token });
    const profile = profilePayload.data;
    window.dapurRapiUserRole = profile.role === "admin" ? "admin" : "user";

    const [
        foodPayload,
        drinkPayload,
        categoryPayload,
        transactionPayload,
        restaurantProfilePayload,
    ] = await Promise.all([
        requestApi(catalogApiPath("makanans"), { token }),
        requestApi(catalogApiPath("minumans"), { token }),
        requestApi(catalogApiPath("kategoris"), { token }),
        requestApi(transactionRequestPath(currentTransactionPage), {
            token,
        }),
        requestApi(
            profile.role === "admin"
                ? "admin/restaurant-profile"
                : "restaurant-profile",
            { token },
        ),
    ]);
    const foods = unwrapData(foodPayload);
    const drinks = unwrapData(drinkPayload);
    const categories = unwrapData(categoryPayload);
    const transactions = unwrapData(transactionPayload);
    const foodTotal = collectionTotal(foodPayload, foods);
    const transactionTotal = Number(
        transactionPayload.active_transactions_count ??
            collectionTotal(transactionPayload, transactions),
    );

    window.dapurRapiFoods = foods;
    window.dapurRapiDrinks = drinks;
    window.dapurRapiCategories = categories;
    renderRestaurantProfile(restaurantProfilePayload.data);
    if (window.dapurRapiUserRole === "admin" && !adminOrderWatchInitialized) {
        latestKnownAdminTransactionId = Math.max(
            0,
            ...transactions.map((transaction) => Number(transaction.id)),
        );
        transactions
            .filter((transaction) => transaction.status === "pending")
            .forEach(showNewOrderNotification);
        adminOrderWatchInitialized = true;
    }
    renderProfile(profile);
    document.querySelector("[data-user-role-label]").textContent =
        window.dapurRapiUserRole === "admin" ? "Administrator" : "Pengguna";
    document.querySelector("[data-profile-open]").hidden =
        window.dapurRapiUserRole !== "user";
    document.querySelector("[data-customer-review-nav]").hidden =
        window.dapurRapiUserRole !== "user";
    document.querySelector("[data-customer-reviews-panel]").hidden =
        window.dapurRapiUserRole !== "user";
    document.querySelector("[data-admin-review-nav]").hidden =
        window.dapurRapiUserRole !== "admin";
    document.querySelector("[data-admin-reviews-panel]").hidden =
        window.dapurRapiUserRole !== "admin";
    document.querySelector("[data-admin-review-metric]").hidden =
        window.dapurRapiUserRole !== "admin";
    document.querySelector("[data-customer-review-metric]").hidden =
        window.dapurRapiUserRole !== "user";
    updateCustomerReviewMetric(profile.ulasans_count ?? 0);
    document.querySelector("[data-admin-stock-panel]").hidden =
        window.dapurRapiUserRole !== "admin";
    document.querySelector("[data-login-activity-nav]").hidden =
        window.dapurRapiUserRole !== "admin";
    document.querySelector("[data-admin-transaction-tools]").hidden =
        window.dapurRapiUserRole !== "admin";
    document.querySelector("[data-create-food]").hidden =
        window.dapurRapiUserRole !== "admin";
    document.querySelector("[data-create-drink]").hidden =
        window.dapurRapiUserRole !== "admin";
    document.querySelector("[data-create-category]").hidden =
        window.dapurRapiUserRole !== "admin";
    document.querySelector('[data-count="foods"]').textContent =
        foodTotal.toLocaleString("id-ID");
    document.querySelector('[data-count="drinks"]').textContent =
        collectionTotal(drinkPayload, drinks).toLocaleString("id-ID");
    document.querySelector("[data-transactions-description]").textContent =
        window.dapurRapiUserRole === "admin"
            ? "Pesanan semua pengguna dan pengelolaan status transaksi."
            : "Riwayat transaksi yang tercatat di akunmu.";
    document.querySelector("[data-transactions-heading]").textContent =
        window.dapurRapiUserRole === "admin"
            ? "Semua pesanan"
            : "Transaksi saya";

    renderOverview(
        foods,
        drinks,
        categories,
        transactions,
        foodTotal + collectionTotal(drinkPayload, drinks),
        collectionTotal(drinkPayload, drinks),
        transactionTotal,
        transactionPayload.top_selling_food,
    );
    renderFoodsTable(foods, foodTotal);
    renderMinumansTable(drinks, collectionTotal(drinkPayload, drinks));
    renderCategories(categories, foods, drinks);
    renderTransactions(transactions, transactionPayload.pagination);

    if (window.dapurRapiUserRole === "user") {
        renderReviewFoodOptions(foods, drinks);
    }

    if (window.dapurRapiUserRole === "admin") {
        await loadAdminReviews(token);
        try {
            const loginActivityPayload = await requestApi(
                "admin/login-activity",
                { token },
            );
            renderLoginActivity(unwrapData(loginActivityPayload));
        } catch {
            setTableMessage(
                "[data-login-activity-rows]",
                "Aktivitas login belum dapat dimuat.",
                4,
            );
        }
    }
}

function openFoodDialog(food = null) {
    const dialog = document.querySelector("[data-food-dialog]");
    const form = document.querySelector("[data-food-form]");
    const categorySelect = document.querySelector("[data-food-category]");

    if (!window.dapurRapiCategories.length) {
        showDashboardNotice(
            "Tambahkan kategori lebih dulu sebelum membuat menu.",
        );
        return;
    }

    categorySelect.innerHTML = window.dapurRapiCategories
        .map(
            (category) =>
                `<option value="${Number(category.id)}">${escapeHtml(category.nama_kategori)}</option>`,
        )
        .join("");
    form.reset();
    form.querySelector('[name="id"]').value = food?.id ?? "";
    document.querySelector("[data-food-dialog-title]").textContent = food
        ? "Ubah makanan"
        : "Tambah makanan";
    document.querySelector('[name="nama_makanan"]').value =
        food?.nama_makanan ?? "";
    document.querySelector('[name="kategori_id"]').value =
        food?.kategori?.id ??
        food?.kategori_id ??
        window.dapurRapiCategories[0].id;
    document.querySelector('[name="harga"]').value = food?.harga ?? "";
    document.querySelector('[name="stok"]').value = food?.stok ?? "";
    document.querySelector('[name="deskripsi"]').value = food?.deskripsi ?? "";
    dialog.showModal();
}

function openCategoryDialog(category = null) {
    const form = document.querySelector("[data-category-form]");
    form.reset();
    document.querySelector("[data-category-id]").value = category?.id ?? "";
    document.querySelector("[data-category-dialog-title]").textContent =
        category ? "Ubah kategori" : "Tambah kategori";
    document.querySelector('[name="nama_kategori"]').value =
        category?.nama_kategori ?? "";
    document.querySelector("[data-category-dialog]").showModal();
}

function openDrinkDialog(drink = null) {
    const form = document.querySelector("[data-drink-form]");
    const categoryField = form.querySelector("[data-drink-category]");
    const drinkCategories = window.dapurRapiCategories.filter((category) =>
        category.nama_kategori
            .toLocaleLowerCase("id-ID")
            .startsWith("minuman "),
    );

    form.reset();
    categoryField.innerHTML = drinkCategories
        .map(
            (category) =>
                `<option value="${Number(category.id)}">${escapeHtml(category.nama_kategori)}</option>`,
        )
        .join("");
    form.querySelector('[name="id"]').value = drink?.id ?? "";
    document.querySelector("[data-drink-dialog-title]").textContent = drink
        ? "Ubah minuman"
        : "Tambah minuman";
    form.querySelector('[name="nama_minuman"]').value =
        drink?.nama_minuman ?? "";
    categoryField.value =
        drink?.kategori?.id ??
        drink?.kategori_id ??
        drinkCategories[0]?.id ??
        "";
    form.querySelector('[name="harga"]').value = drink?.harga ?? "";
    form.querySelector('[name="stok"]').value = drink?.stok ?? "";
    form.querySelector('[name="deskripsi"]').value = drink?.deskripsi ?? "";
    document.querySelector("[data-drink-dialog]").showModal();
}

function setPurchaseStep(step) {
    const isPaymentStep = step === "payment";
    document.querySelectorAll("[data-purchase-step]").forEach((section) => {
        section.hidden = section.dataset.purchaseStep !== step;
    });
    document.querySelector("[data-purchase-eyebrow]").textContent =
        isPaymentStep ? "LANGKAH 2 DARI 2" : "LANGKAH 1 DARI 2";
    document.querySelector("[data-purchase-title]").textContent = isPaymentStep
        ? "Pembayaran"
        : "Pesanan";
}

function updatePaymentSummary() {
    const product = getSelectedPurchaseProduct();
    const quantity = Number(document.querySelector('[name="qty"]').value);
    if (!product) return;

    const total = Number(product.harga) * quantity;
    document.querySelector("[data-payment-item-name]").textContent =
        product.nama_makanan ?? product.nama_minuman;
    document.querySelector("[data-payment-item-quantity]").textContent =
        `${quantity} × ${formatCurrency(product.harga)}`;
    document.querySelector("[data-payment-total]").textContent =
        formatCurrency(total);
    updatePaymentInstructions();
}

function getSelectedPurchaseProduct() {
    const drinkId = Number(
        document.querySelector("[data-purchase-minuman-id]").value,
    );

    if (drinkId) {
        return window.dapurRapiDrinks.find(
            (drink) => Number(drink.id) === drinkId,
        );
    }

    const foodId = Number(document.querySelector("[data-purchase-id]").value);

    return window.dapurRapiFoods.find((food) => Number(food.id) === foodId);
}

function openPurchaseDialog(product, isDrink = false) {
    document.querySelector("[data-purchase-id]").value = isDrink
        ? ""
        : product.id;
    document.querySelector("[data-purchase-minuman-id]").value = isDrink
        ? product.id
        : "";
    document.querySelector("[data-purchase-name]").textContent =
        product.nama_makanan ?? product.nama_minuman;
    document.querySelector("[data-purchase-price]").textContent =
        `${formatCurrency(product.harga)} per porsi · stok ${product.stok} unit`;

    const quantity = document.querySelector('[name="qty"]');
    quantity.max = product.stok;
    quantity.value = "1";

    const paymentSettings = JSON.parse(
        document.querySelector("[data-payment-settings]").textContent,
    );
    const defaultMethod = paymentSettings.banks.length
        ? "bank_transfer"
        : paymentSettings.qrisPayload
          ? "qris"
          : "cash_on_pickup";
    document.querySelector(
        `[name="payment_method"][value="${defaultMethod}"]`,
    ).checked = true;
    updatePaymentInstructions();
    setPurchaseStep("details");
    updatePaymentSummary();
    document.querySelector("[data-purchase-dialog]").showModal();
}

function updatePaymentInstructions() {
    const method = document.querySelector(
        '[name="payment_method"]:checked',
    )?.value;
    const settings = JSON.parse(
        document.querySelector("[data-payment-settings]").textContent,
    );
    const bankPanel = document.querySelector("[data-bank-payment-panel]");
    const qrisPanel = document.querySelector("[data-qris-payment-panel]");
    const accountDetails = document.querySelector(
        "[data-payment-account-details]",
    );
    const account = settings.banks[0];
    bankPanel.hidden = method !== "bank_transfer";
    qrisPanel.hidden = method !== "qris";
    accountDetails.hidden = method !== "bank_transfer" || !account;
    document.querySelector("[data-bank-unavailable]").hidden =
        method !== "bank_transfer" || Boolean(account);

    if (account) {
        document.querySelector("[data-payment-bank-name]").textContent =
            account.name;
        document.querySelector("[data-payment-account-number]").textContent =
            account.account_number;
        document.querySelector("[data-payment-account-name]").textContent =
            account.account_name;
    }

    const qrisImage = document.querySelector("[data-qris-code]");
    const qrisUnavailable = document.querySelector("[data-qris-unavailable]");
    const hasQrisImage = Boolean(settings.qrisImageUrl);
    const hasQrisSource = hasQrisImage || Boolean(settings.qrisPayload);
    qrisImage.hidden = method !== "qris" || !hasQrisSource;
    qrisUnavailable.hidden = method !== "qris" || hasQrisSource;

    if (method === "qris" && hasQrisSource) {
        qrisImage.src = hasQrisImage
            ? settings.qrisImageUrl
            : `https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=${encodeURIComponent(settings.qrisPayload)}`;
    }

    const instructions = {
        bank_transfer: account
            ? `Transfer tepat ${document.querySelector("[data-payment-total]").textContent} dari bank mana pun ke rekening ${account.name} yang tertera.`
            : "Transfer bank belum aktif.",
        qris: hasQrisSource
            ? "Pindai QRIS merchant, lalu bayar sesuai total pesanan. Pembayaran belum diverifikasi otomatis."
            : "QRIS belum dikonfigurasi pengelola.",
        cash_on_pickup:
            "Pesanan dicatat menunggu pembayaran. Siapkan pembayaran tunai saat mengambil pesanan.",
    };

    document.querySelector("[data-payment-instructions]").textContent =
        instructions[method] ?? instructions.cash_on_pickup;
    document.querySelector("[data-payment-confirm]").disabled =
        (method === "bank_transfer" && !account) ||
        (method === "qris" && !hasQrisSource);
}

function initializePaymentMethods() {
    const settings = JSON.parse(
        document.querySelector("[data-payment-settings]").textContent,
    );
    const bankMethod = document.querySelector(
        '[name="payment_method"][value="bank_transfer"]',
    );
    const qrisMethod = document.querySelector(
        '[name="payment_method"][value="qris"]',
    );

    bankMethod.disabled = false;
    qrisMethod.disabled = false;
    document.querySelector("[data-bank-method-hint]").textContent =
        settings.banks.length > 0
            ? "Transfer dari bank mana pun ke rekening tujuan ini"
            : "Rekening tujuan belum tersedia";
    document.querySelector("[data-qris-method-hint]").textContent =
        settings.qrisImageUrl || settings.qrisPayload
            ? "Pindai QRIS merchant"
            : "Belum tersedia: QRIS merchant belum dikonfigurasi";

    updatePaymentInstructions();
}

function initializeDashboardActions() {
    document.querySelectorAll("[data-restaurant-brand]").forEach((brand) => {
        brand.addEventListener("click", (event) => {
            event.preventDefault();
            openRestaurantDialog();
        });
    });

    const logoLightbox = document.querySelector("[data-logo-lightbox]");
    const largeLogo = document.querySelector("[data-logo-lightbox-image]");
    const openLogo = (image) => {
        if (!image.src) return;

        largeLogo.src = image.currentSrc || image.src;
        largeLogo.alt = image.alt;
        logoLightbox.showModal();
    };

    document.querySelectorAll("[data-logo-zoom]").forEach((image) => {
        image.addEventListener("click", () => openLogo(image));
        image.addEventListener("keydown", (event) => {
            if (event.key === "Enter" || event.key === " ") {
                event.preventDefault();
                openLogo(image);
            }
        });
    });

    document
        .querySelector("[data-logo-lightbox-close]")
        ?.addEventListener("click", () => logoLightbox.close());
    logoLightbox?.addEventListener("click", (event) => {
        if (event.target === logoLightbox) logoLightbox.close();
    });

    document
        .querySelector("[data-restaurant-logo-input]")
        ?.addEventListener("change", (event) => {
            const file = event.currentTarget.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.addEventListener("load", () => {
                const preview = document.querySelector(
                    "[data-restaurant-editor-logo]",
                );
                preview.src = reader.result;
                preview.hidden = false;
                document.querySelector("[data-editor-logo-mark]").hidden = true;
            });
            reader.readAsDataURL(file);
        });

    document
        .querySelector("[data-restaurant-form]")
        ?.addEventListener("submit", async (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            const button = form.querySelector('button[type="submit"]');
            const error = document.querySelector(
                "[data-restaurant-form-error]",
            );
            const formData = new FormData(form);
            formData.set("name", String(formData.get("name")).trim());
            formData.set(
                "history",
                String(formData.get("history") ?? "").trim(),
            );

            error.hidden = true;
            button.disabled = true;

            try {
                const selectedLogo = formData.get("logo");
                if (
                    selectedLogo instanceof File &&
                    selectedLogo.size > restaurantLogoMaxBytes
                ) {
                    formData.set(
                        "logo",
                        await optimizeRestaurantLogo(selectedLogo),
                    );
                }

                const payload = await requestApi(
                    window.dapurRapiUserRole === "admin"
                        ? "admin/restaurant-profile"
                        : "restaurant-profile",
                    {
                        method: "POST",
                        token: window.dapurRapiToken,
                        body: formData,
                    },
                );
                renderRestaurantProfile(payload.data);
                document.querySelector("[data-restaurant-dialog]").close();
                showDashboardNotice(payload.message, "success");
            } catch (requestError) {
                error.textContent = requestError.message;
                error.hidden = false;
            } finally {
                button.disabled = false;
            }
        });

    document.querySelector("[data-recent-foods]")?.addEventListener(
        "scroll",
        () => {
            const grid = document.querySelector("[data-recent-foods]");
            const pageCount = Math.ceil(
                grid.querySelectorAll(".recent-card").length / 4,
            );
            const maxScroll = grid.scrollWidth - grid.clientWidth;

            if (pageCount > 1 && maxScroll > 0) {
                recentCarouselPage = Math.round(
                    (grid.scrollLeft / maxScroll) * (pageCount - 1),
                );
            }

            updateRecentCarouselControls();
        },
        { passive: true },
    );
    document
        .querySelector("[data-recent-slider]")
        ?.addEventListener("input", (event) => {
            moveRecentCarouselToPage(Number(event.currentTarget.value));
        });

    if (!document.querySelector("[data-dashboard]")) return;

    document
        .querySelector("[data-review-rating]")
        ?.addEventListener("input", (event) => {
            const input = event.currentTarget;
            const rating = Number(input.value);
            const progress =
                ((rating - Number(input.min)) /
                    (Number(input.max) - Number(input.min))) *
                100;
            input.dataset.ratingTouched = "true";
            input.style.setProperty("--rating-progress", `${progress}%`);
            const output = document.querySelector(
                "[data-review-rating-output]",
            );

            output.innerHTML = `${rating.toLocaleString("id-ID", { maximumFractionDigits: 1 })} <small>/ 10</small>`;
            updateReviewSubmitState();
        });

    document
        .querySelector("[data-review-food]")
        ?.addEventListener("change", updateReviewSubmitState);

    document
        .querySelector("[data-review-form]")
        ?.addEventListener("submit", async (event) => {
            event.preventDefault();
            if (window.dapurRapiUserRole !== "user") return;

            const form = event.currentTarget;
            const button = form.querySelector('button[type="submit"]');
            const values = Object.fromEntries(new FormData(form));
            const [menuType, menuId] = String(values.menu_item).split(":");
            if (
                form.querySelector("[data-review-rating]").dataset
                    .ratingTouched !== "true"
            ) {
                showDashboardNotice(
                    "Geser penilaian terlebih dahulu sebelum mengirim ulasan.",
                );
                return;
            }
            button.disabled = true;

            try {
                await requestApi("ulasans", {
                    method: "POST",
                    token: window.dapurRapiToken,
                    body: JSON.stringify({
                        makanan_id:
                            menuType === "makanan" ? Number(menuId) : null,
                        minuman_id:
                            menuType === "minuman" ? Number(menuId) : null,
                        rating: Number(values.rating),
                        komentar: values.komentar.trim() || null,
                    }),
                });
                updateCustomerReviewMetric(customerReviewCount + 1);
                form.reset();
                const ratingInput = form.querySelector("[data-review-rating]");
                ratingInput.dataset.ratingTouched = "false";
                ratingInput.style.setProperty("--rating-progress", "0%");
                document.querySelector(
                    "[data-review-rating-output]",
                ).innerHTML = "— <small>/ 10</small>";
                updateReviewSubmitState();
                showDashboardNotice("Ulasanmu berhasil dibagikan.", "success");
            } catch (error) {
                showDashboardNotice(error.message);
            } finally {
                updateReviewSubmitState();
            }
        });

    document
        .querySelector("[data-admin-review-rows]")
        ?.addEventListener("submit", async (event) => {
            const form = event.target.closest("[data-review-reply-form]");
            if (!form || window.dapurRapiUserRole !== "admin") return;

            event.preventDefault();
            const reviewId = Number(form.dataset.reviewReplyForm);
            const button = document.querySelector(
                `[data-review-reply-submit="${reviewId}"]`,
            );
            const reply = new FormData(form).get("balasan_admin").trim();
            button.disabled = true;

            try {
                await requestApi(`admin/ulasans/${reviewId}/reply`, {
                    method: "PUT",
                    token: window.dapurRapiToken,
                    body: JSON.stringify({ balasan_admin: reply }),
                });
                await loadAdminReviews(window.dapurRapiToken);
                showDashboardNotice("Balasan berhasil dikirim.", "success");
            } catch (error) {
                button.disabled = false;
                showDashboardNotice(error.message);
            }
        });

    initializePaymentMethods();
    document
        .querySelector("[data-admin-order-notifications]")
        ?.addEventListener("click", async (event) => {
            const dismissButton = event.target.closest(
                "[data-dismiss-order-notice]",
            );
            if (dismissButton) {
                dismissButton.closest("[data-admin-order-notice]")?.remove();
                updateAdminOrderNotificationVisibility();
                return;
            }

            const approveButton = event.target.closest(
                "[data-approve-transaction]",
            );
            if (approveButton) {
                await approvePendingTransaction(
                    Number(approveButton.dataset.approveTransaction),
                    approveButton,
                );
            }
        });
    document
        .querySelector("[data-transaction-rows]")
        ?.addEventListener("click", async (event) => {
            const approveButton = event.target.closest(
                "[data-approve-transaction]",
            );
            if (approveButton) {
                await approvePendingTransaction(
                    Number(approveButton.dataset.approveTransaction),
                    approveButton,
                );
            }
        });
    let transactionSearchTimeout;
    document
        .querySelector("[data-transaction-search]")
        ?.addEventListener("input", () => {
            window.clearTimeout(transactionSearchTimeout);
            transactionSearchTimeout = window.setTimeout(
                () => loadTransactionPage(1),
                250,
            );
        });
    document
        .querySelector("[data-transaction-status-filter]")
        ?.addEventListener("change", () => loadTransactionPage(1));
    document
        .querySelector("[data-transaction-payment-filter]")
        ?.addEventListener("change", () => loadTransactionPage(1));
    document
        .querySelector("[data-transaction-previous]")
        ?.addEventListener("click", () =>
            loadTransactionPage(Math.max(1, currentTransactionPage - 1)),
        );
    document
        .querySelector("[data-transaction-next]")
        ?.addEventListener("click", () =>
            loadTransactionPage(currentTransactionPage + 1),
        );

    document
        .querySelector("[data-profile-open]")
        ?.addEventListener("click", () => {
            const nameField = document.querySelector("[data-profile-name]");
            nameField.value =
                document.querySelector("[data-user-name]").textContent;
            document.querySelector("[data-profile-dialog]").showModal();
        });

    document
        .querySelector("[data-profile-form]")
        ?.addEventListener("submit", async (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            const button = form.querySelector('button[type="submit"]');
            const name = new FormData(form).get("name").trim();

            button.disabled = true;
            try {
                const payload = await requestApi("me/profile", {
                    method: "PUT",
                    token: window.dapurRapiToken,
                    body: JSON.stringify({ name }),
                });
                renderProfile(payload.data);
                sessionStorage.setItem(
                    authUserKey,
                    JSON.stringify(payload.data),
                );
                document.querySelector("[data-profile-dialog]").close();
                showDashboardNotice(
                    "Nama profil berhasil diperbarui.",
                    "success",
                );
            } catch (error) {
                showDashboardNotice(error.message);
            } finally {
                button.disabled = false;
            }
        });

    document
        .querySelector("[data-create-food]")
        ?.addEventListener("click", () => {
            openFoodDialog();
        });
    document
        .querySelector("[data-create-drink]")
        ?.addEventListener("click", () => openDrinkDialog());
    document
        .querySelector("[data-create-category]")
        ?.addEventListener("click", () => openCategoryDialog());

    let drinkSearchTimeout;
    document
        .querySelector("[data-drink-search]")
        ?.addEventListener("input", (event) => {
            const query = event.currentTarget.value.trim();
            window.clearTimeout(drinkSearchTimeout);
            drinkSearchTimeout = window.setTimeout(async () => {
                try {
                    const payload = await requestApi(
                        `${catalogApiPath("minumans")}?search=${encodeURIComponent(query)}`,
                        { token: window.dapurRapiToken },
                    );
                    const drinks = unwrapData(payload);
                    window.dapurRapiDrinks = drinks;
                    renderMinumansTable(
                        drinks,
                        collectionTotal(payload, drinks),
                    );
                } catch (error) {
                    showDashboardNotice(error.message);
                }
            }, 250);
        });

    document
        .querySelector("[data-drink-form]")
        ?.addEventListener("submit", async (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            const button = form.querySelector('button[type="submit"]');
            const values = Object.fromEntries(new FormData(form));
            const drinkId = values.id;
            const payload = {
                nama_minuman: values.nama_minuman,
                kategori_id: Number(values.kategori_id),
                harga: Number(values.harga),
                stok: Number(values.stok),
                deskripsi: values.deskripsi,
            };

            button.disabled = true;
            try {
                await requestApi(
                    drinkId
                        ? catalogApiPath("minumans", drinkId)
                        : catalogApiPath("minumans"),
                    {
                        method: drinkId ? "PUT" : "POST",
                        token: window.dapurRapiToken,
                        body: JSON.stringify(payload),
                    },
                );
                document.querySelector("[data-drink-dialog]").close();
                showDashboardNotice(
                    drinkId
                        ? "Perubahan minuman tersimpan."
                        : "Minuman berhasil ditambahkan ke tabel terpisah.",
                    "success",
                );
                await loadDashboardData(window.dapurRapiToken);
            } catch (error) {
                showDashboardNotice(error.message);
            } finally {
                button.disabled = false;
            }
        });

    document
        .querySelector("[data-drink-rows]")
        ?.addEventListener("click", async (event) => {
            const button = event.target.closest("button");
            if (!button) return;

            const drinkId = Number(
                button.dataset.drinkEdit ??
                    button.dataset.drinkDelete ??
                    button.dataset.drinkBuy,
            );
            const drink = window.dapurRapiDrinks.find(
                (item) => Number(item.id) === drinkId,
            );
            if (!drink) return;

            if (button.hasAttribute("data-drink-edit")) {
                openDrinkDialog(drink);
                return;
            }

            if (button.hasAttribute("data-drink-delete")) {
                if (
                    !window.confirm(
                        `Hapus ${drink.nama_minuman} dari daftar minuman?`,
                    )
                )
                    return;

                try {
                    await requestApi(catalogApiPath("minumans", drinkId), {
                        method: "DELETE",
                        token: window.dapurRapiToken,
                    });
                    showDashboardNotice("Minuman berhasil dihapus.", "success");
                    await loadDashboardData(window.dapurRapiToken);
                } catch (error) {
                    showDashboardNotice(error.message);
                }
                return;
            }

            if (button.hasAttribute("data-drink-buy")) {
                openPurchaseDialog(drink, true);
            }
        });

    document.querySelectorAll("[data-close-dialog]").forEach((button) => {
        button.addEventListener("click", () =>
            button.closest("dialog").close(),
        );
    });

    document
        .querySelector("[data-food-rows]")
        ?.addEventListener("click", async (event) => {
            const button = event.target.closest("button");
            if (!button) return;

            const foodId = Number(
                button.dataset.foodEdit ??
                    button.dataset.foodDelete ??
                    button.dataset.foodBuy,
            );
            const food = window.dapurRapiFoods.find(
                (item) => Number(item.id) === foodId,
            );
            if (!food) return;

            if (button.hasAttribute("data-food-edit")) {
                openFoodDialog(food);
                return;
            }

            if (button.hasAttribute("data-food-delete")) {
                if (
                    !window.confirm(
                        `Hapus ${food.nama_makanan} dari daftar makanan?`,
                    )
                )
                    return;

                try {
                    await requestApi(catalogApiPath("makanans", foodId), {
                        method: "DELETE",
                        token: window.dapurRapiToken,
                    });
                    showDashboardNotice("Makanan berhasil dihapus.", "success");
                    await loadDashboardData(window.dapurRapiToken);
                } catch (error) {
                    showDashboardNotice(error.message);
                }
                return;
            }

            if (button.hasAttribute("data-food-buy")) {
                openPurchaseDialog(food);
            }
        });

    document
        .querySelector('[name="qty"]')
        ?.addEventListener("input", updatePaymentSummary);
    document
        .querySelectorAll('[name="payment_method"]')
        .forEach((option) =>
            option.addEventListener("change", updatePaymentInstructions),
        );

    document
        .querySelector("[data-payment-next]")
        ?.addEventListener("click", () => {
            const quantity = Number(
                document.querySelector('[name="qty"]').value,
            );
            const product = getSelectedPurchaseProduct();

            if (!product || quantity < 1 || quantity > Number(product.stok)) {
                showDashboardNotice(
                    "Jumlah pesanan melebihi stok yang tersedia.",
                );
                return;
            }

            updatePaymentSummary();
            setPurchaseStep("payment");
        });

    document
        .querySelector("[data-payment-back]")
        ?.addEventListener("click", () => setPurchaseStep("details"));

    document
        .querySelector("[data-category-grid]")
        ?.addEventListener("click", async (event) => {
            const button = event.target.closest("button");
            if (!button) return;

            const categoryId = Number(
                button.dataset.categoryEdit ?? button.dataset.categoryDelete,
            );
            const category = window.dapurRapiCategories.find(
                (item) => Number(item.id) === categoryId,
            );
            if (!category) return;

            if (button.hasAttribute("data-category-edit")) {
                openCategoryDialog(category);
                return;
            }

            if (!window.confirm(`Hapus kategori ${category.nama_kategori}?`))
                return;

            try {
                await requestApi(catalogApiPath("kategoris", categoryId), {
                    method: "DELETE",
                    token: window.dapurRapiToken,
                });
                showDashboardNotice("Kategori berhasil dihapus.", "success");
                await loadDashboardData(window.dapurRapiToken);
            } catch (error) {
                showDashboardNotice(error.message);
            }
        });

    document
        .querySelector("[data-transaction-rows]")
        ?.addEventListener("click", async (event) => {
            const approveButton = event.target.closest(
                "[data-transaction-approve]",
            );
            if (!approveButton) return;

            const transactionId = Number(
                approveButton.dataset.transactionApprove,
            );
            approveButton.disabled = true;

            try {
                await requestApi(`admin/transaksis/${transactionId}/status`, {
                    method: "PUT",
                    token: window.dapurRapiToken,
                    body: JSON.stringify({ status: "diproses" }),
                });
                showDashboardNotice(
                    "Pesanan disetujui dan sedang diproses.",
                    "success",
                );
                await loadDashboardData(window.dapurRapiToken);
            } catch (error) {
                approveButton.disabled = false;
                showDashboardNotice(error.message);
            }
        });

    document
        .querySelector("[data-transaction-rows]")
        ?.addEventListener("change", async (event) => {
            const paymentSelect = event.target.closest(
                "[data-transaction-payment-status]",
            );

            if (paymentSelect) {
                const transactionId = Number(
                    paymentSelect.dataset.transactionPaymentStatus,
                );
                const previousStatus = paymentSelect.dataset.currentStatus;
                const status = paymentSelect.value;
                if (status === previousStatus) return;

                paymentSelect.disabled = true;
                try {
                    await requestApi(
                        `admin/transaksis/${transactionId}/payment-status`,
                        {
                            method: "PUT",
                            token: window.dapurRapiToken,
                            body: JSON.stringify({ status }),
                        },
                    );
                    showDashboardNotice(
                        "Status pembayaran berhasil diperbarui.",
                        "success",
                    );
                    await loadDashboardData(window.dapurRapiToken);
                } catch (error) {
                    paymentSelect.value = previousStatus;
                    showDashboardNotice(error.message);
                } finally {
                    paymentSelect.disabled = false;
                }
                return;
            }

            const select = event.target.closest("[data-transaction-status]");
            if (!select) return;

            const transactionId = Number(select.dataset.transactionStatus);
            const previousStatus = select.dataset.currentStatus;
            const status = select.value;
            if (status === previousStatus) return;

            select.disabled = true;
            try {
                await requestApi(`admin/transaksis/${transactionId}/status`, {
                    method: "PUT",
                    token: window.dapurRapiToken,
                    body: JSON.stringify({ status }),
                });
                showDashboardNotice(
                    "Status transaksi berhasil diperbarui.",
                    "success",
                );
                await loadDashboardData(window.dapurRapiToken);
            } catch (error) {
                select.value = previousStatus;
                showDashboardNotice(error.message);
            } finally {
                select.disabled = false;
            }
        });

    document
        .querySelector("[data-food-form]")
        ?.addEventListener("submit", async (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            const button = form.querySelector('button[type="submit"]');
            const values = Object.fromEntries(new FormData(form));
            const foodId = values.id;
            const payload = {
                nama_makanan: values.nama_makanan,
                kategori_id: Number(values.kategori_id),
                harga: Number(values.harga),
                stok: Number(values.stok),
                deskripsi: values.deskripsi,
            };

            button.disabled = true;
            try {
                await requestApi(
                    foodId
                        ? catalogApiPath("makanans", foodId)
                        : catalogApiPath("makanans"),
                    {
                        method: foodId ? "PUT" : "POST",
                        token: window.dapurRapiToken,
                        body: JSON.stringify(payload),
                    },
                );
                document.querySelector("[data-food-dialog]").close();
                showDashboardNotice(
                    foodId
                        ? "Perubahan makanan tersimpan."
                        : "Makanan berhasil ditambahkan.",
                    "success",
                );
                await loadDashboardData(window.dapurRapiToken);
            } catch (error) {
                showDashboardNotice(error.message);
            } finally {
                button.disabled = false;
            }
        });

    document
        .querySelector("[data-category-form]")
        ?.addEventListener("submit", async (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            const button = form.querySelector('button[type="submit"]');
            const values = Object.fromEntries(new FormData(form));
            const categoryId = values.id;

            button.disabled = true;
            try {
                await requestApi(
                    categoryId
                        ? catalogApiPath("kategoris", categoryId)
                        : catalogApiPath("kategoris"),
                    {
                        method: categoryId ? "PUT" : "POST",
                        token: window.dapurRapiToken,
                        body: JSON.stringify({
                            nama_kategori: values.nama_kategori,
                        }),
                    },
                );
                document.querySelector("[data-category-dialog]").close();
                showDashboardNotice(
                    categoryId
                        ? "Perubahan kategori tersimpan."
                        : "Kategori berhasil ditambahkan.",
                    "success",
                );
                await loadDashboardData(window.dapurRapiToken);
            } catch (error) {
                showDashboardNotice(error.message);
            } finally {
                button.disabled = false;
            }
        });

    document
        .querySelector("[data-purchase-form]")
        ?.addEventListener("submit", async (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            const button = form.querySelector('button[type="submit"]');
            const foodId = Number(
                document.querySelector("[data-purchase-id]").value,
            );
            const drinkId = Number(
                document.querySelector("[data-purchase-minuman-id]").value,
            );
            const quantity = Number(
                document.querySelector('[name="qty"]').value,
            );
            const product = getSelectedPurchaseProduct();

            if (!product || quantity < 1 || quantity > Number(product.stok)) {
                showDashboardNotice(
                    "Jumlah pesanan melebihi stok yang tersedia.",
                );
                return;
            }

            const paymentMethod = document.querySelector(
                '[name="payment_method"]:checked',
            ).value;

            if (
                !document.querySelector('[data-purchase-step="payment"]').hidden
            ) {
                button.disabled = true;
                try {
                    const purchasePayload = await requestApi("transaksis", {
                        method: "POST",
                        token: window.dapurRapiToken,
                        body: JSON.stringify({
                            items: [
                                {
                                    ...(drinkId
                                        ? { minuman_id: drinkId }
                                        : { makanan_id: foodId }),
                                    qty: quantity,
                                },
                            ],
                            payment_method: paymentMethod,
                        }),
                    });
                    addPendingApprovalId(purchasePayload.data.id);
                    document.querySelector("[data-purchase-dialog]").close();
                    showDashboardNotice(
                        "Pesanan berhasil dikirim. Menunggu persetujuan admin sebelum muncul di transaksi.",
                        "order-confirmation",
                    );
                    await loadDashboardData(window.dapurRapiToken);
                    startUserApprovalPolling();
                } catch (error) {
                    showDashboardNotice(error.message);
                } finally {
                    button.disabled = false;
                }
                return;
            }
        });
}

async function initializeDashboard() {
    const dashboard = document.querySelector("[data-dashboard]");
    if (!dashboard) return;

    const token = sessionStorage.getItem(authTokenKey);
    if (!token) {
        window.location.replace("/login");
        return;
    }
    window.dapurRapiToken = token;

    document.querySelector("[data-date]").textContent = new Intl.DateTimeFormat(
        "id-ID",
        {
            weekday: "long",
            day: "numeric",
            month: "long",
            year: "numeric",
        },
    ).format(new Date());

    try {
        await loadDashboardData(token);
        if (window.dapurRapiUserRole === "admin" && !adminOrderPollingTimer) {
            adminOrderPollingTimer = window.setInterval(
                pollForNewAdminOrders,
                8000,
            );
        } else if (window.dapurRapiUserRole !== "admin") {
            startUserApprovalPolling();
        }
    } catch (error) {
        if (/unauthorized|unauthenticated/i.test(error.message)) {
            sessionStorage.removeItem(authTokenKey);
            sessionStorage.removeItem(authUserKey);
            window.location.replace("/login");
            return;
        }

        showDashboardNotice(
            error.message ||
                "Data belum dapat dimuat. Periksa koneksi lalu muat ulang halaman.",
        );
        setTableMessage(
            "[data-low-stock-rows]",
            "Data stok belum dapat dimuat.",
            5,
        );
        setTableMessage(
            "[data-food-rows]",
            "Data makanan belum dapat dimuat.",
            6,
        );
        setTableMessage(
            "[data-transaction-rows]",
            "Data transaksi belum dapat dimuat.",
            4,
        );
        document.querySelector("[data-category-grid]").innerHTML =
            '<div class="empty-inline">Data kategori belum dapat dimuat.</div>';
    }
}

initializeAuthForms();
initializeLoginLogo();
initializeNavigation();
initializeDashboardActions();
initializeDashboard();
initializeTunnelReload();
