document.addEventListener('DOMContentLoaded', function () {
    let userId = null;
    let currentPage = 1;
    const limit = 10;
    var isCommentOpen = false;
    const container = document.querySelector('.cards');
    const comments_list = document.getElementById('comments_list');
    const modal = document.getElementsByClassName('modal')[0];
    const open_modal = document.getElementById('openModal');
    const close_modal = document.getElementById('closeModal');
    const notification = document.getElementById('notification');
    const errorDiv = document.getElementById('error');
    const register = document.getElementById('register');
    const logout = document.getElementById('logout');
    const page = document.querySelector('#pagination a');
    const count = document.getElementById('post_count')
    const avatarIcon = document.getElementById('avatarIcon');
    // document.getElementById('comment_section').remove();
    if (window.localStorage.getItem("userId") && window.localStorage.getItem("userName")) {
        userId = window.localStorage.getItem("userId");
        register.remove();
        logout.style.display = "inline-block";
        document.getElementById('profile').style.display = "block";
        open_modal.style.display = 'inline-block';
        let name = document.querySelector('#username span');
        name.innerHTML = window.localStorage.getItem("userName");
        avatarIcon.src = '/images/user.png';
        document.getElementById('warning').remove();
    }
    else {
        document.getElementById('warning').style.display = "flex";
        register.style.display = "inline-block";
        logout.style.display = "none";
        document.getElementById('profile').remove();
        open_modal.remove();
    }
    // container.addEventListener('change', HandleCardsChange);
    open_modal.addEventListener('click', openModal);
    close_modal.addEventListener('click', closeModal);
    register.addEventListener('click', handleRegister);
    logout.addEventListener('click', logOut);
    document.getElementById('pagination_container').addEventListener('click', changePage);

    function changePage(event) {
        const target = event.target;
        if (target.matches('.pagination a')) {
            event.preventDefault();
            currentPage = target.dataset['page'];
            container.innerHTML = '';
            fetchPosts(currentPage);
            // return false;
        }
    }
    function HandleCardsChange() {
        const buttonsCollection = document.querySelectorAll('button:has(img)');
        if (!window.localStorage.getItem("userId")) {
            for (const el of buttonsCollection) {
                el.remove();
            }
        }
    }
    function openModal(event) {
        modal.style.display = "block";
    }
    function closeModal(event) {
        modal.style.display = "none";
    }
    async function fetchPosts(page = 1) {
        try {
            const response = await fetch('api.php?get_action=getPosts', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ user_id: userId, page: page })
            });

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const data = await response.json();
            if (data.success) {
                const total_pages = data['total_pages'];
                count.textContent = data['total_result'];
                addCardsInChunks(data['posts'], 10, undefined, container, false);
                addPages(total_pages);
            } else {
                console.error('Error:', data.error);
                return [];
            }
        } catch (error) {
            console.error('Error:', error);
            return [];
        }
    }

    function addPages(total_pages) {
        var list = document.getElementById('pagination');
        let pageElement;
        let pages = [];
        while (list.firstChild) { list.removeChild(list.firstChild) }
        for (let i = 1; i <= total_pages; i++) {
            var li = document.createElement('li');
            li.innerHTML = `<li><a href="#" data-page="${i}">${i}</a></li>`;
            list.appendChild(li);
        }
    }

    function createCard(post, isComment) {
        const li = document.createElement('li');
        let f = window.localStorage.getItem('userId') ? true : false;
        let messageButton;
        if (isComment) messageButton = '';
        else messageButton = f ? `<td><button class="message"><img class="icon" src="images/message.png"></button></td>` : '';
        let upvoteButton = f ? `<td><button class="upvote" data-voted= ${post.user_vote === "upvote"}><img class="icon" src="images/angle-up.png"></button></td>` : '';
        let downvoteButton = f ? `<td><button class="downvote" data-voted= ${post.user_vote === "downvote"}><img class="icon" src="images/angle-down.png"></button></td>` : '';
        // let comment_section = f ? '<div class="comment_section"><div class="comments_list"></div><textarea class="comment_input" placeholder="Введите комментарий"></textarea><input type="submit" class="submit_comment" value="Отправить"></input></div>' : '';
        li.innerHTML = `
            <div class="card" data-id="${post.id}">
                <div class="author"><span>${post.author}</span></div>
                <div class="content"><span>${post.content}</span></div>
                <hr />
                <div class="card_footer">
                    <div class="info"><span class="date">${post.date}</span></div>
                    <table class="actions">
                        <tr>
                            ${messageButton}
                            ${upvoteButton}
                            <td class="score"><span>${post.score}</span></td>
                            ${downvoteButton}
                        </tr>
                    </table>
                </div>
            </div>
        `;
        return li;
    }
    function addCardsInChunks(posts, chunkSize = 1, flag = 0, container, isComment) {
        let index = 0;
        function addNextChunk() {
            const fragment = document.createDocumentFragment();
            for (let i = 0; i < chunkSize && index < posts.length; i++) {
                const card = createCard(posts[index], isComment);
                fragment.appendChild(card);
                index++;
            }
            if (flag) container.prepend(fragment);
            else container.appendChild(fragment);
            HandleCardsChange();
            if (index < posts.length) {
                requestAnimationFrame(addNextChunk);
            } else {
                attachEventListeners();
            }
        }
        requestAnimationFrame(addNextChunk);
    }
    function attachEventListeners() {
        document.querySelectorAll('.message').forEach(button =>
            button.addEventListener('click', handleMessageClick)
        );
        document.querySelectorAll('.upvote').forEach(button =>
            button.addEventListener('click', handleVoteClick)
        );
        document.querySelectorAll('.downvote').forEach(button =>
            button.addEventListener('click', handleVoteClick)
        );
        document.querySelectorAll("input[type='submit']").forEach(submit =>
            submit.addEventListener('click', putPost)
        );
    }
    fetchPosts(currentPage);
    function handleMessageClick(event) {
        const card = event.currentTarget.closest('.card');
        container.innerHTML = '';
        container.appendChild(card);
        if (!isCommentOpen) {
            isCommentOpen = true;
            fetchComments(card.dataset['id']);
            document.getElementById('comments_section').classList.remove('hidden');
        }
        else {
            isCommentOpen = false;
            fetchPosts(currentPage);
        }
    };
    function fetchComments(cardId) {
        comments_list.innerHTML = '';
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'api.php?get_action=getComments', true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.send(JSON.stringify({
            card_id: cardId,
        }));

        xhr.onload = function () {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const response = JSON.parse(xhr.response);
                    comments = response['comments'];
                    addCardsInChunks(comments, 10, undefined, comments_list, true);

                }
                catch (e) { console.error('Error parsing JSON: ', e); }
            }
        }

    }
    function handleVoteClick(event) {
        const button = event.currentTarget;
        const ul = button.closest('ul');
        let newScore;
        let action;
        const currentVoted = button.getAttribute('data-voted') === 'true';
        const card = button.closest('.card');
        const scoreElement = card.querySelector('.score span');
        let score = parseInt(scoreElement.textContent, 10);
        const cardId = card.getAttribute('data-id');
        let oldCLass;
        switch (button.className) {
            case 'upvote':
                oldCLass = 'upvote';
                var otherAction = card.querySelector('.downvote');
                if (otherAction.getAttribute('data-voted') === 'true') otherAction.setAttribute('data-voted', 'false');
                action = currentVoted ? 'unvote' : 'upvote';
                newScore = (action === oldCLass) ? score + 1 : score - 1;
                break;

            case 'downvote':
                oldCLass = 'downvote';
                var otherAction = card.querySelector('.upvote');
                if (otherAction.getAttribute('data-voted') === 'true') otherAction.setAttribute('data-voted', 'false');
                action = currentVoted ? 'unvote' : 'downvote';
                newScore = (action === oldCLass) ? score - 1 : score + 1;
                break;
        }
        if (action === 'unvote') {
            button.setAttribute('data-voted', 'false')
        }
        else { button.setAttribute('data-voted', 'true') }
        const xhr = new XMLHttpRequest();
        if (ul.className !=='comments_list') {
            xhr.open('POST', 'api.php?get_action=update', true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.send(JSON.stringify({
                user_id: userId,
                card_id: cardId,
                action: action
            }));
            xhr.onload = function () {
                // console.log(JSON.parse(xhr.response));
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        const response = JSON.parse(xhr.response);
                        updateScore(cardId, response['newScore']);

                    } catch (e) {
                        console.error('Error parsing JSON:', e);
                    }
                } else {
                    console.error('Request failed with status:', xhr.status);
                }
            };
            xhr.onerror = function () {
                console.error('Request error');
            };
        }
        else {
            xhr.open('POST', 'api.php?get_action=update', true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.send(JSON.stringify({
                user_id: userId,
                comment_id: cardId,
                action: action
            }));
            xhr.onload = function () {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        const response = JSON.parse(xhr.response);
                        updateScore(cardId, response['newScore']);

                    } catch (e) {
                        console.error('Error parsing JSON:', e);
                    }
                } else {
                    console.error('Request failed with status:', xhr.status);
                }
            };
            xhr.onerror = function () {
                console.error('Request error');
            };
        }
        function updateScore(postId, newScore) {
            document.querySelector(`.card[data-id="${postId}"] .card_footer .actions tr .score span`).textContent = newScore;
        }
    }
    function putPost() {
        let form = document.forms.putPost;
        const formData = new FormData(form);
        const content = formData.get('text').trim();
        if (!validateMessage(content)) {
            return;
        }
        console.log(content);
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'api.php?get_action=putPost', true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.send(JSON.stringify({
            user_id: userId,
            content: content,
        }));
        let data;
        xhr.onload = function () {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const response = JSON.parse(xhr.response);
                    data = response['data'];
                    // console.log(data);
                    form.reset();
                    showNotification();
                    container.innerHTML = '';
                    fetchPosts(currentPage);
                    addCardsInChunks(posts, undefined, 1);
                } catch (e) {
                    console.error('Error parsing JSON:', e);
                }
            } else {
                console.error('Request failed with status:', xhr.status);
            }
        }
        xhr.onerror = function () {
            console.error('Request error');
        };

        modal.style.display = 'none';
    }
    function showNotification() {
        notification.classList.add('show');
        setTimeout(() => {
            notification.classList.add('hide');
        }, 1000);
        setTimeout(() => {
            notification.classList.remove('show');
            notification.classList.remove('hide');
        }, 1500);
    }
    function validateMessage(message) {
        const minLength = 10;
        const maxLength = 1000;
        errorDiv.textContent = '';

        if (message.length < minLength) {
            errorDiv.classList.add('show');
            errorDiv.textContent = `Сообщение должно содержать не менее ${minLength} символов.`;
            return false;
        }

        if (message.length > maxLength) {
            errorDiv.classList.add('show');
            errorDiv.textContent = `Сообщение должно содержать не более ${maxLength} символов.`;
            return false;
        }
        errorDiv.classList.remove("show");
        return true;
    }

    function handleRegister() {
        window.location = '/auth/register.html';
    }
    function logOut() {
        window.localStorage.clear();
        location.reload();
    }
});
