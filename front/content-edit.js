import { get, post, REFRESH_TOKEN_KEY, TOKEN_KEY } from "./http.js";
import { editBlockHandler, setBlockReadOnly } from "./text-edit.js";

let contentData;
try {
  contentData = await get("content");
} catch {
  console.warn("Ошибка загрузки контента");
}
const contentBlocks = document.querySelectorAll("[data-content-id]");
const authModal = new bootstrap.Modal("#myModal");

const onDocumentClick = ({ target }) => {
  const editableTextBlock = target.closest("[data-content-id]");
  const editableImgBlock = target.closest("[data-img-content-id]");

  const isActiveEditableBlock =
  editableTextBlock?.classList.contains("active") ||
    editableImgBlock?.classList.contains("active");

  if (isActiveEditableBlock) {
    return;
  }

  hideActiveBlocks();

  if (editableTextBlock) {
    editBlockHandler(editableTextBlock);
    return;
  }

  if (editableImgBlock) {
    console.log("Img!");
    return;
  }

  const isExitBtn = target.closest(".admin-panel__btn");
  if (isExitBtn) {
    setAdmin(false);
    return;
  }
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
    document.addEventListener("click", onDocumentClick);
    return;
  }

  sessionStorage.removeItem(TOKEN_KEY);
  sessionStorage.removeItem(REFRESH_TOKEN_KEY);
  document.removeEventListener("click", onDocumentClick);
  document.querySelector(".admin-panel")?.remove();
}

function hideActiveBlocks() {
  const activeTextBlock = document.querySelector("[data-content-id].active");
  if (activeTextBlock) {
    setBlockReadOnly(activeTextBlock);
  }
}
