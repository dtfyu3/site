var isCommentOpen = false;
var isEditOpen = false;
const default_notification_text = 'Сообщение успешно отправлено!';
function createSpinner(container) {
    const spinner_container = document.createElement("div");
    spinner_container.id = 'loading-spinner';
    const spinner = document.createElement("div");
    spinner.classList.add('spinner');
    spinner_container.appendChild(spinner);
    if (!container.classList.contains('comments_section')) container.appendChild(spinner_container);
    else {
        container.prepend(spinner_container);
        spinner_container.style.backgroundColor = 'rgba(255, 255, 255, 0.8)';
    }
}
function removeSpinner() {
    if (document.getElementById('loading-spinner')) document.getElementById('loading-spinner').remove();
}
function closeComments(currentScroll) {
    const onScroll = () => {
        if (window.scrollY === currentScroll) {
            window.removeEventListener('scroll', onScroll); // Удаляем обработчик события
            document.getElementById('comments_section').classList.add('hidden'); // Скрываем секцию
            comments_list.innerHTML = ''; // Очищаем список комментариев
        }
    };

    // Добавляем обработчик события scroll
    window.addEventListener('scroll', onScroll);
    window.scrollTo({
        top: currentScroll,
        behavior: 'smooth'
    });
}
function hideCards(card, isCommentOpen) {
    let sibling = card.closest('li').nextElementSibling;
    if (!isCommentOpen) {
        while (sibling) {
            sibling.style.display = "none";
            sibling = sibling.nextElementSibling;
        }
    }
    else {
        while (sibling) {
            sibling.style.display = "list-item";
            sibling = sibling.nextElementSibling;
        }
    }
}
function toggleTheme() {
    document.body.style.transition = 'all 0.2s ease-in-out';
    document.body.classList.toggle("dark-theme");
    if (document.body.classList.contains("dark-theme")) {
        localStorage.setItem("theme", "dark");
    } else {
        localStorage.setItem("theme", "light");
    }
}

document.addEventListener('DOMContentLoaded', function () {
    let userId = null;
    let currentPage;
    const limit = 10;

    let currentScroll = 0;
    const container = document.querySelector('.cards');
    const comments_list = document.getElementById('comments_list');
    const modal = document.getElementsByClassName('modal')[0];
    const open_modal = document.getElementById('openModal');
    const close_modal = document.getElementById('closeModal');
    const notification = document.getElementById('notification');
    const errorDiv = document.getElementById('error');
    const errorComment = document.getElementById('error_comment');
    const register = document.getElementById('register');
    const logout = document.getElementById('logout');
    // const page = document.querySelector('#pagination a');
    const count = document.getElementById('post_count')
    const avatarIcon = document.getElementById('avatarIcon');
    const submitComment = document.getElementById('submit_comment');
    const textarea = document.querySelector('.comments_section textarea');
    const searchButton = document.getElementById('searchButton');
    const resetButton = document.getElementById('resetButton');
    const searchInput = document.getElementById('searchInput');
    const select = document.querySelector('.order select');

    if (localStorage.getItem("theme") === "dark") {
        document.body.classList.add("dark-theme");
    }
    document.querySelector('.theme-toggle').addEventListener('click', toggleTheme);
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
        textarea.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); submitComment.click(); }
        });
    }
    else {
        document.getElementById('warning').style.display = "flex";
        register.style.display = "inline-block";
        logout.style.display = "none";
        document.getElementById('profile').remove();
        open_modal.remove();
    }

    // resetButton.disabled = true;
    // resetButton.classList.add('disabled');
    // searchButton.disabled = true;
    // searchButton.classList.add('disabled');

    open_modal.addEventListener('click', openModal);
    close_modal.addEventListener('click', closeModal);
    register.addEventListener('click', handleRegister);
    logout.addEventListener('click', logOut);
    submitComment.addEventListener('click', putComment);
    // document.getElementById('pagination_container').addEventListener('click', changePage);
    // document.querySelectorAll('#pagination a').forEach (a => a.addEventListener('click',changePage));
    searchInput.addEventListener('input', handleSearchInput);
    searchButton.addEventListener('click', search);
    resetButton.addEventListener('click', resetSearch);
    select.addEventListener('change', (event) => {
        const search = searchInput.value.trim();
        if (search != '' && search != null) fetchPosts(currentPage, undefined, undefined, search);
        else fetchPosts(currentPage);
    });

    function changePage(event) {
        const target = event.target;
        document.querySelector('.pagination .current').classList.remove('current');
        if (target.matches('.pagination a')) {
            event.preventDefault();
            let card = container.querySelector('.card:last-of-type');
            if (isEditOpen) closeEdit(card);
            if (isCommentOpen) {
                isCommentOpen = !isCommentOpen;
                comments_list.innerHTML = '';
                // hideCards(card, isCommentOpen);
                document.getElementById('comments_section').classList.add('hidden');
            }
            container.innerHTML = '';
            currentPage = parseInt(target.dataset['page']);
            const search = searchInput.value.trim();
            fetchPosts(currentPage, undefined, undefined, search).then(() => {
                const a = document.querySelector(`.pagination a[data-page="${currentPage}"]`);
                a.classList.add('current');
            });

        }
    }
    function HandleCardsChange() {
        const buttonsCollection = document.querySelectorAll('.card button:has(img)');
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
    function getPageCount() {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'api.php?get_action=getPageCount', true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.send(JSON.stringify({}));
        xhr.onload = function () {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const response = JSON.parse(xhr.response);
                    const page_count = response['page_count'];
                    addPages(page_count, page_count);
                    currentPage = page_count;
                    fetchPosts(currentPage);
                }
                catch (e) {
                    console.error('Error parsing JSON:', e);
                }
            }
        }
    }
    async function fetchPosts(page = 1, limit = null, offset = false, query = null, callback) {
        if (offset == false) container.innerHTML = '';
        let order;
        let option = select.selectedOptions[0].value;
        if (option == 'asc') order = 'asc';
        else order = null;
        createSpinner(document.body);
        try {
            const response = await fetch('api.php?get_action=getPosts', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ user_id: userId, page: page, limit: limit, offset: offset, query: query, order: order })
            });

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            const data = await response.json();
            if (data.success) {
                if (!callback) {
                    const total_pages = data['total_pages'];
                    count.textContent = data['total_result'];
                    if (data['posts'].length > 0) {
                        addCardsInChunks(data['posts'], 10, undefined, container, false);
                        if (total_pages != document.getElementById('pagination').children.length) addPages(total_pages, page);
                    }
                    else {
                        const span = document.createElement('span');
                        span.textContent = "Ничего не найдено";
                        container.appendChild(span);
                    }
                }
                else {
                    callback(data['posts']);
                }
            } else {
                console.error('Error:', data.error);
                return [];
            }
        } catch (error) {
            console.error('Error:', error);
            return [];
        }
        finally {
            removeSpinner();
        }
    }

    function addPages(total_pages, page = currentPage) {
        var list = document.getElementById('pagination');
        while (list.firstChild) { list.removeChild(list.firstChild) }
        for (let i = 1; i <= total_pages; i++) {
            var li = document.createElement('li');
            li.innerHTML = `<a href="#" data-page="${i}">${i}</a>`;
            li.addEventListener('click', changePage);
            list.appendChild(li);
        }
        const a = document.querySelector(`.pagination a[data-page="${page}"]`);
        a.classList.add('current');
        currentPage = page;
    }

    function createCard(post, isComment) {
        const li = document.createElement('li');
        let f = window.localStorage.getItem('userId') ? true : false;
        let messageButton;
        const userName = f ? window.localStorage.getItem('userName') : null;
        let res = parseFloat(post.score) / parseFloat(post.total_votes);
        let spanClass = '';
        let comment_counter = '';
        let comment_count = '';
        const [dateStr, time] = post.date.split(' ');
        var date = new Date(dateStr + 'T' + time);
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        date = `${day}.${month}.${year}`;
        post.content = post.content.replace(/\n/g, '<br>');
        const edited = (post.edit_date != null) ? '<div class="edited" id="edited">Ред.</div>' : '<div class="edited" id="edited"></div>';
        const divider = (userName !== null && userName === post.author) ? '<div class="divider"></div>' : '';
        let deleteDiv = (userName != null && userName === post.author) ? '<div class="delete_container"><button class="delete"><img src="images/delete.png" class="icon"></img></button></div>' : '';
        let editDiv = (userName != null && userName === post.author && !isComment) ? '<div class="edit_container"><button class="edit"><img src="images/edit.png" class="icon"></img></button></div>' : '';
        if (!isComment) {
            comment_counter = post.comment_count ? post.comment_count : 0;
            comment_count = `<td class="comment_count"><span>${comment_counter}</span></td>`;
            if (res > 0.5) spanClass = 'positive';
            else if (res < 0.5) spanClass = 'negative';
            else { spanClass = '' };
            messageButton = f ? `<td><button class="message"><img class="icon" src="images/message.png"></button></td>` : 'Комментариев:';
        }
        if (isComment) messageButton = '';
        let upvoteButton = f ? `<td><button class="upvote" data-voted= ${post.user_vote === "upvote"}><img class="icon" src="images/angle-up.png"></button></td>` : '';
        let downvoteButton = f ? `<td><button class="downvote" data-voted= ${post.user_vote === "downvote"}><img class="icon" src="images/angle-down.png"></button></td>` : '';
        li.innerHTML = `
            <div class="card" data-id="${post.id}" data-score="${post.score}" data-total_votes="${post.total_votes}">
            <div class="card_header">
            <div class="author"><span>${post.author}</span></div>
            <div class="card_header_buttons">
            ${editDiv}
            ${divider}
            ${deleteDiv}
            </div>
            </div>
                <div class="content"><span>${post.content}</span></div>
                ${edited}
                <hr />
                <div class="card_footer">
                    <div class="info"><span class="date">${date} ${time}</span></div>
                    <table class="actions">
                        <tr>
                            ${comment_count}
                            ${messageButton}
                            ${upvoteButton}
                            <td class="score"><span class="${spanClass}">${post.score}</span></td>
                            ${downvoteButton}
                        </tr>
                    </table>
                </div>
            </div>
        `;
        return li;
    }
    function addCardsInChunks(posts, chunkSize = 1, flag = 0, container, isComment) {
        return new Promise((resolve) => {
            let index = 0;
            function addNextChunk() {
                const fragment = document.createDocumentFragment();
                for (let i = 0; i < chunkSize && index < posts.length; i++) {
                    const card = createCard(posts[index], isComment);
                    fragment.appendChild(card);
                    index++;
                }
                if (index => posts.length) resolve();
                if (!flag) container.prepend(fragment);
                else container.appendChild(fragment);
                HandleCardsChange();
                if (index < posts.length) {
                    requestAnimationFrame(addNextChunk);
                } else {
                    attachEventListeners();
                }

            }
            requestAnimationFrame(addNextChunk);
        });
    }
    function handleScoreChange(target) {
        span = target;
        const card = span.closest('.card');
        span.className = '';
        let res = parseFloat(card.dataset['score']) / parseFloat(card.dataset['total_votes']);
        if (res > 0.5) span.className = 'positive';
        else if (res < 0.5) span.className = 'negative';
        else span.className = '';
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
        const sp = document.querySelectorAll('.score span');
        document.querySelectorAll('.score span').forEach(span =>
            span.addEventListener('oncnahge', handleScoreChange)
        );
        if (window.localStorage.getItem('userName')) {
            const deleteButton = document.querySelectorAll('.delete');
            const editButton = document.querySelectorAll('.edit');
            deleteButton.forEach(button =>
                button.addEventListener('click', handleDelete)
            );
            editButton.forEach(button => {
                button.addEventListener('click', handleEdit)
            })
        }
    }
    getPageCount();
    function handleMessageClick(event) {
        const card = event.currentTarget.closest('.card');
        const notification = document.getElementById('notification');
        if (isEditOpen) {
            showNotification('Сначала закройте форму редактирования!', 250, 600, '#af8a39');
            return;
        }
        if (!isCommentOpen) {
            currentScroll = window.scrollY;
            hideCards(card, isCommentOpen);
            fetchComments(card.dataset['id'], false);
            document.getElementById('comments_section').classList.remove('hidden');
            comments_list.classList.remove('hidden');
            isCommentOpen = true;
            scrollIfNeeded();
            // if(card.querySelector('.comment_count span').textContent > 0){
            //     fetchComments(card.dataset['id'], false,comments=>{
            //         addCardsInChunks(comments, 10, undefined, comments_list, true);
            //         observeCommentsLoading(comments_list, () => {
            //             scrollIfNeeded(); 
            //         });
            //     });
            // }
            // else{

            // }
        }
        else {
            hideCards(card, isCommentOpen);
            isCommentOpen = false;
            // setTimeout(() => {
            //     window.scrollTo({
            //         top: currentScroll,
            //         behavior: 'smooth' 
            //     });
            // }, 0);
            // document.getElementById('comments_section').classList.add('hidden');
            // comments_list.innerHTML = '';
            closeComments(currentScroll);
        }

    };

    // function observeCommentsLoading(commentsListElement, callback) {
    //     const observer = new MutationObserver((mutations) => {
    //         let commentsAdded = false;
    //         mutations.forEach(mutation => {
    //             if (mutation.addedNodes.length > 0) {
    //                 commentsAdded = true;
    //             }
    //         });
    //         if (commentsAdded) {
    //             observer.disconnect();
    //             callback(); 
    //         }
    //     });

    //     observer.observe(commentsListElement, { childList: true, subtree: true });
    // }

    function scrollIfNeeded() {
        const pageHeight = document.documentElement.scrollHeight;
        const windowHeight = window.innerHeight;
        if (pageHeight > windowHeight) {
            window.scrollTo({
                top: pageHeight,
                behavior: 'smooth'
            });
        }
    }

    function delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
    async function fetchComments(cardId, flag, callback) {
        let card = document.querySelector(`.cards .card[data-id="${cardId}"]`);
        if (card.querySelector('.comment_count span').textContent > 0) {
            createSpinner(document.getElementById('comments_section'));
            // await delay(3000);
            comments_list.innerHTML = '';
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'api.php?get_action=getComments', true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.send(JSON.stringify({
                card_id: cardId,
                user_id: userId
            }));

            xhr.onload = async function () {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        const response = JSON.parse(xhr.response);
                        comments = response['comments'];
                        if (!callback) await addCardsInChunks(comments, 10, undefined, comments_list, true);
                        else {
                            callback(comments);
                        }
                    }
                    catch (e) { console.error('Error parsing JSON: ', e); }
                    finally { removeSpinner(); scrollIfNeeded(); }
                }
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
        if (ul.className !== 'comments_list') {
            xhr.open('POST', 'api.php?get_action=update', true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.send(JSON.stringify({
                user_id: userId,
                card_id: cardId,
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
                        updateScore(cardId, response['newScore'], true);

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
        function updateScore(postId, newScore, isComment = false) {
            const list = !isComment ? container : comments_list;
            let card = list.querySelector(`.card[data-id="${postId}"]`);
            card.querySelector('.card_footer .actions tr .score span').textContent = newScore;
            card.dataset['score'] = newScore;
            handleScoreChange(card.querySelector('.card_footer .actions tr .score span'));
        }
    }
    function putPost() {
        let form = document.forms.putPost;
        const formData = new FormData(form);
        const content = formData.get('text').trim();
        if (!validateMessage(content, false)) {
            return;
        }
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'api.php?get_action=putPost', true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.send(JSON.stringify({
            user_id: userId,
            content: content,
        }));
        let data;
        xhr.onload = async function () {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const response = JSON.parse(xhr.response);
                    data = response['data'];
                    form.reset();
                    const arr = [];
                    arr.push(data);
                    showNotification(default_notification_text);
                    const childcount = container.querySelectorAll('li').length;
                    if (childcount + 1 <= limit) { await addCardsInChunks(arr, undefined, 1, container, false); scrollIfNeeded(); }
                    if (childcount + 1 > limit) { //if card to be added overfill the page
                        // container.removeChild(container.lastElementChild); //then remove last card
                        if (response['total_pages'] > document.getElementById('pagination').children.length) { //if insert leads to new page to be added
                            addPages(response['total_pages'], response['total_pages']);   //then get new number of pages
                            const a = document.querySelector(`.pagination a[data-page="${response['total_pages']}"]`);
                            a.click();
                        }
                    }
                    count.textContent = response['total_result'];
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
    function putComment(event) {
        const cards = container.querySelectorAll('li:not([style*="display: none"])');
        const card = cards[cards.length - 1].querySelector('.card');
        const textarea = event.currentTarget.closest('.comments_actions').querySelector('textarea');
        const content = textarea.value;
        if (validateMessage(content, true)) {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'api.php?get_action=putComment', true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.send(JSON.stringify({
                user_id: userId,
                card_id: card.dataset['id'],
                content: content
            }));
            xhr.onload = async function () {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        const response = JSON.parse(xhr.response);
                        data = response['data'];
                        data['author'] = window.localStorage.getItem('userName');
                        const arr = [];
                        arr.push(data);
                        await addCardsInChunks(arr, undefined, 0, comments_list, true);
                        updateCommentsCount(card, response['comments_count']);
                        showNotification(default_notification_text);
                        textarea.value = '';
                        scrollIfNeeded();
                    }
                    catch (e) { console.error('Error parsing JSON: ', e); }
                }
            }
        }
    }
    function updateCommentsCount(card, comments_count) {
        const c = card.querySelector('.comment_count span');
        c.textContent = '';
        c.textContent = comments_count;

    }
    function showNotification(text, t1 = 1000, t2 = 1500, background = '#4CAF50') {
        notification.classList.add('show');
        notification.innerHTML = text;
        notification.innerText = text;
        notification.style.backgroundColor = background;
        setTimeout(() => {
            notification.classList.add('hide');
        }, t1);
        setTimeout(() => {
            notification.classList.remove('show');
            notification.classList.remove('hide');
        }, t2);
    }
    function validateMessage(message, isComment) {
        var error = errorComment;
        var minLength = 1;
        if (!isComment) {
            minLength = 10;
            error = errorDiv;
        };
        const maxLength = 1500;
        error.textContent = '';
        if (message.length < minLength) {
            error.classList.add('show');
            error.classList.remove('hide');
            setTimeout(() => {
                error.classList.add('hide');
            }, 1500);
            setTimeout(() => {
                error.classList.remove('show');
            }, 1500);
            error.textContent = `Сообщение должно содержать не менее ${minLength} символов.`;
            return false;
        }

        if (message.length > maxLength) {
            error.classList.add('show');
            error.classList.remove('hide');
            setTimeout(() => {
                error.classList.add('hide');
            }, 1500);
            setTimeout(() => {
                error.classList.remove('show');
            }, 1500);
            error.textContent = `Сообщение должно содержать не более ${maxLength} символов.`;
            return false;
        }
        error.classList.remove("show");
        return true;
    }

    function handleRegister() {
        window.location = '/auth/register.html';
    }
    function logOut() {
        window.localStorage.clear();
        location.reload();
    }

    function handleDelete(event) {
        const card = event.currentTarget.closest('.card');
        if (card.parentElement.parentElement.classList.contains('comments_list')) {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'api.php?get_action=delete', true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.send(JSON.stringify({
                comment_id: card.dataset['id']
            }));
            xhr.onload = function () {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        const response = JSON.parse(xhr.response);
                        if (response.success === true) {
                            const childToRemove = comments_list.querySelector('[data-id="' + card.dataset['id'] + '"]').closest('li');
                            comments_list.removeChild(childToRemove);
                            const cards = container.querySelectorAll('li:not([style*="display: none"])');
                            const parentCard = cards[cards.length - 1].querySelector('.card');
                            let comment_count = parseInt(parentCard.querySelector('.comment_count span').textContent) - 1;
                            updateCommentsCount(parentCard, comment_count);
                        }
                    }
                    catch (e) { console.error('Error parsing JSON: ', e); }
                }
            }
        }
        else {
            if (confirm('Вы действительно хотите удалить данную запись?')) {
                if (isCommentOpen) {
                    document.getElementById('comments_section').classList.add('hidden');
                    hideCards(card, isCommentOpen);
                    isCommentOpen = false;
                }
                const comment_count = card.querySelector('.comment_count span').textContent > 0;
                if (comment_count) {
                    fetchComments(card.dataset['id'], true, comments => {
                        const arr = [];
                        comments.forEach(comment => { arr.push(comment['id']) });
                        del(arr);
                    })
                }
                else {
                    document.getElementById('comments_section').classList.add('hidden');
                    isCommentOpen = false;
                    del();
                }
            }
        }
        function del(arr = null) {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'api.php?get_action=delete', true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.send(JSON.stringify({
                card_id: card.dataset['id'],
                comment_list: arr
            }));
            xhr.onload = function () {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        const response = JSON.parse(xhr.response);
                        if (response.success === true) {
                            const childToRemove = container.querySelector('[data-id="' + card.dataset['id'] + '"]').closest('li');
                            container.removeChild(childToRemove);
                            count.textContent = parseInt(count.textContent) - 1;
                            // if (container.querySelectorAll('li').length < limit && response['total_pages'] > 1) {
                            //     fetchPosts(parseInt(currentPage), 1, true, null, post => {
                            //         addCardsInChunks(post, undefined, 0, container, false);
                            //     })
                            // }
                            if (response['total_pages'] != document.getElementById('pagination').children.length) {
                                fetchPosts(parseInt(currentPage), 1, true, null, post => {
                                    addCardsInChunks(post, undefined, 1, container, false);
                                    addPages(response['total_pages'], response['total_pages']);
                                    const a = document.querySelector(`.pagination a[data-page="${response['total_pages']}"]`);
                                    // a.click();
                                })
                            }
                        }
                    }
                    catch (e) { console.error('Error parsing JSON: ', e); }
                }
            }
        }
    }

    function handleEdit(event) {
        const card = event.currentTarget.closest('.card');
        if (isCommentOpen) {
            showNotification('Сначала закройте комментарии!', 250, 600, '#af8a39');
            return;
        }
        if (!isEditOpen) {
            currentScroll = window.scrollY;
            isEditOpen = true;
            let text = card.querySelector('.content').innerText;
            hideCards(card, false);
            const form = document.createElement('form');
            form.id = 'editForm';
            const textarea = document.createElement('textarea');
            const submit = document.createElement('input');
            const close = document.createElement('span');
            const div = document.createElement('div');
            close.textContent = close_modal.textContent;
            close.classList.add('closeModal');
            textarea.textContent = text;
            submit.type = 'submit';
            submit.style.display = 'inline-block'
            close.style.display = 'inline-block';
            submit.style.margin = 0;
            div.appendChild(submit);
            div.appendChild(close);
            form.append(textarea);
            form.append(div);
            form.onsubmit = "return false";
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                updateCard(textarea.value, card.dataset['id'], card);
            });
            close.addEventListener('click', function () {
                closeEdit(card);
            });
            container.append(form);
            scrollIfNeeded();
        }
        else {
            closeEdit(card);
            setTimeout(() => {
                window.scrollTo({
                    top: currentScroll,
                    behavior: 'smooth'
                });
            }, 0);
        }

        function updateCard(text, card_id, card) {
            {
                // this.preventDefault
                if (!validateMessage(text, false)) {
                    return;
                }
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'api.php?get_action=updateCard', true);
                xhr.setRequestHeader('Content-Type', 'application/json');
                xhr.send(JSON.stringify({
                    card_id: card_id,
                    content: text
                }));
                xhr.onload = function () {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        try {
                            const response = JSON.parse(xhr.response);
                            if (response.success === true) {
                                showNotification(default_notification_text);
                                card.querySelector('.content').innerText = text;
                                let edited = card.querySelector('.edited');
                                edited.innerText = 'Ред.'
                                document.getElementById('editForm').remove();
                                isEditOpen = !isEditOpen;
                                hideCards(card, true);
                            }
                        }
                        catch (e) {
                            console.error('Error parsing JSON:', e);
                        }
                    }
                    else {
                        console.error('Request failed with status:', xhr.status);
                    }
                }
            }
        }
    }
    function closeEdit(card) {
        isEditOpen = false;
        container.querySelector('#editForm').remove();
        if (!isCommentOpen) hideCards(card, true);
    }
    function search() {
        const searchForm = document.forms.searchForm;
        const formData = new FormData(searchForm);
        let query = formData.get('query').trim();
        if (query != null && query != '') {
            searchInput.dataset['searched'] = true;
            fetchPosts(undefined, undefined, undefined, query);
        }
        resetButton.disabled = false;
        resetButton.classList.remove('disabled');
    }
    function resetSearch() {
        if (searchInput.dataset['searched'] === 'true') {
            searchInput.value = '';
            searchButton.disabled = true;
            resetButton.disabled = true;
            resetButton.classList.add('disabled');
            searchButton.classList.add('disabled');
            searchInput.dataset['searched'] = false;
            getPageCount();
        }
    }
    function handleSearchInput() {
        if (searchInput.value == '') {
            searchButton.disabled = true;
            searchButton.classList.add('disabled');
            if (searchInput.dataset['searched'] === 'true') {
                resetButton.disabled = true;
                resetButton.classList.add('disabled');
                resetSearch();
                searchInput.dataset['searched'] = false;
            }
        }
        else {
            if (searchInput.dataset['searched'] === 'true') {
                resetButton.disabled = false;
                resetButton.classList.remove('disabled');
            }
            searchButton.disabled = false;
            searchButton.classList.remove('disabled');
        }

        // if (searchInput.value == '' && searchInput.dataset['searched'] === 'true') {
        //     resetButton.disabled = true;
        //     resetButton.classList.add('disabled');
        //     searchButton.disabled = true;
        //     searchButton.classList.add('disabled');
        //     searchInput.dataset['searched'] = false;
        //     resetSearch();
        // } else {
        //     resetButton.disabled = false;
        //     resetButton.classList.remove('disabled');
        //     searchButton.disabled = false;
        //     searchButton.classList.remove('disabled');
        // }
    }
});
