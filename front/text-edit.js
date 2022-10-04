import { put } from "./http.js";

export const editBlockHandler = (editableBlock) => {
  if (!editableBlock) {
    return;
  }

  setBlockEditable(editableBlock);
};

function setBlockEditable(block) {
  block.setAttribute("contenteditable", "true");
  block.setAttribute("data-init-value", block.innerText);
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

export function setBlockReadOnly(block, newValue) {
  if (!block) {
    return;
  }

  block.querySelector(".edit-actions")?.remove();
  block.classList.remove("active");
  block.setAttribute("contenteditable", "false");
  const initValue = block.dataset.initValue;
  block.removeAttribute("data-init-value");
  if (newValue) {
    block.innerHTML = newValue;
    return;
  }
  block.innerHTML = initValue;
}
