import { get, post, put, REFRESH_TOKEN_KEY, TOKEN_KEY } from "./http.js";

let contentData;
try {
  contentData = await get("content");
} catch {
  console.warn("Ошибка загрузки контента");
}
const contentBlocks = document.querySelectorAll("[data-content-id]");
const authModal = new bootstrap.Modal("#myModal");

const editBlockHandler = (event) => {
  const editableBlock = event.target.closest("[data-content-id]");
  const isActiveEditableBlock = event.target.closest(
    "[data-content-id].active"
  );
  const isExitBtn = event.target.closest(".admin-panel__btn");

  if (isExitBtn) {
    setAdmin(false);
  }

  if (!isActiveEditableBlock) {
    const activeBlock = document.querySelector("[data-content-id].active");
    if (activeBlock) {
      setBlockReadOnly(activeBlock);
    }
  }

  if (!editableBlock) {
    return;
  }

  setBlockEditable(editableBlock);
};

setAdmin(!!sessionStorage.getItem(TOKEN_KEY));

contentBlocks.forEach((block) => {
  const blockData = contentData?.find((d) => d.id == block.dataset.contentId);
  if (blockData) {
    block.innerText = blockData.value;
  }
});

document.addEventListener("keydown", (event) => {
  if (event.code == "KeyM" && event.ctrlKey) {
    authModal.show();
  }
});

enterBtn.addEventListener("click", () => {
  post("login", { password: password.value })
    .then((tokens) => {
      sessionStorage.setItem(TOKEN_KEY, tokens.token);
      sessionStorage.setItem(REFRESH_TOKEN_KEY, tokens.refreshToken);
      setAdmin(true);
      authModal.hide();
    })
    .catch(() => {
      alert("Неверный пароль");
    });
});

function setAdmin(isAdmin) {
  if (isAdmin) {
    const div = document.createElement("div");
    div.innerHTML = `
        <div class="admin-panel d-flex align-items-center justify-content-between px-3 py-1">
            <span>Выполнен вход администратора</span>
            <button class="btn btn-outline-light admin-panel__btn">Выйти</button>
        </div>
    `;
    document.body.insertBefore(
      div.firstElementChild,
      document.body.firstElementChild
    );
    document.addEventListener("click", editBlockHandler);
    return;
  }

  sessionStorage.removeItem(TOKEN_KEY);
  sessionStorage.removeItem(REFRESH_TOKEN_KEY);
  document.removeEventListener("click", editBlockHandler);
  document.querySelector(".admin-panel")?.remove();
}

function setBlockEditable(block) {
  block.setAttribute("contenteditable", "true");
  block.classList.add("active");
  block.focus();

  const div = document.createElement("div");
  div.innerHTML = `
    <div class="d-flex edit-actions">
        <button class="btn btn-success edit-actions__action edit-actions__action_accept bi bi-check"></button>
        <button class="btn btn-danger edit-actions__action edit-actions__action_decline bi bi-x"></button>
    </div>
  `;

  const editActionsBlock = div.firstElementChild;
  block.appendChild(editActionsBlock);
  editActionsBlock.addEventListener("click", onEditActionClick);
}

function onEditActionClick({ target }) {
  const isAccept = target.closest(".edit-actions__action_accept");
  const editBlock = target.closest("[data-content-id]");
  if (!isAccept) {
    setBlockReadOnly(editBlock);
    return;
  }

  put("content", {
    id: editBlock.dataset.contentId,
    value: editBlock.innerText,
  }).then(() => {
    setBlockReadOnly(editBlock, editBlock.innerText);
  });
}

function setBlockReadOnly(block, value) {
  if (!block) {
    return;
  }

  block.querySelector(".edit-actions")?.remove();
  block.classList.remove("active");
  block.setAttribute("contenteditable", "false");
  const data = contentData?.find((d) => d.id == block.dataset.contentId);
  if (data && value) {
    data.value = value;
  }
  block.innerHTML = value || data?.value;
}
