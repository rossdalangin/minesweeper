(function( $ ) {
    'use strict';

    $(function() {
        console.log('Quiz Sweeper JS Initializing...');

        if (typeof quiz_sweeper_student_ajax === 'undefined') {
            console.error('Quiz Sweeper Error: Localized data object not found. The game cannot start.');
            return;
        }

        console.log('Localized data found:', quiz_sweeper_student_ajax);

        var ajaxUrl = quiz_sweeper_student_ajax.ajax_url;
        var nonce = quiz_sweeper_student_ajax.nonce;
        var gameId = quiz_sweeper_student_ajax.game_id;
        var myGroupId = quiz_sweeper_student_ajax.group_id;

        var gameStateInterval;
        var boardEl = $('#quiz-sweeper-board');
        var scoresEl = $('#quiz-sweeper-scores');

        // Delegated event handler for cell clicks
        boardEl.on('click', '.cell:not(.revealed)', function() {
            var clickedCell = $(this);
            var row = clickedCell.data('row');
            var col = clickedCell.data('col');
            handleCellClick(row, col);
        });

        function getGameState() {
            console.log('Calling getGameState()...');
            $.get(ajaxUrl, { action: 'get_game_state', nonce: nonce, game_id: gameId }, function(response) {
                console.log('getGameState() response received:', response);
                if (response.success) {
                    console.log('getGameState() success. Rendering board and scores.');
                    renderBoard(response.data.grid);
                    renderScores(response.data.scores);

                    var allRevealed = response.data.grid.every(function(cell) { return cell.is_revealed == 1; });
                    if (allRevealed && response.data.grid.length > 0) {
                        console.log('Game Over detected.');
                        clearInterval(gameStateInterval);
                        showGameOver(response.data.scores);
                    }
                } else {
                    console.error('getGameState() failed:', response.data ? response.data.message : 'Unknown error.');
                }
            });
        }

        function renderBoard(grid) {
            console.log('renderBoard() called.');
            boardEl.empty();
            grid.forEach(function(cellData) {
                var cellEl = $('<div class="cell"></div>');
                cellEl.attr('data-row', cellData.row);
                cellEl.attr('data-col', cellData.col);

                if (cellData.is_revealed) {
                    cellEl.addClass('revealed');
                    var icon = '';
                    switch(cellData.type) {
                        case 'bomb': icon = '💣'; break;
                        case 'knife': icon = '🔪'; break;
                        case 'question':
                            if (cellData.was_correct == 1) {
                                icon = '✅';
                                cellEl.addClass('correct');
                            } else {
                                icon = '❌';
                                cellEl.addClass('incorrect');
                            }
                            break;
                        case 'empty': icon = ' '; break;
                    }
                    cellEl.html('<span class="cell-icon">' + icon + '</span>');
                }
                boardEl.append(cellEl);
            });
        }

        function renderScores(scores) {
            console.log('renderScores() called.');
            var html = '<h3>Scores</h3><ul>';
            scores.forEach(function(scoreData) {
                var isMyGroup = scoreData.group_id == myGroupId ? ' (Your Group)' : '';
                html += '<li>' + scoreData.group_name + isMyGroup + ': ' + scoreData.score + '</li>';
            });
            html += '</ul>';
            scoresEl.html(html);
        }

        function handleCellClick(row, col) {
            console.log('handleCellClick() called for cell:', row, col);
            var data = {
                action: 'get_question_details',
                nonce: nonce,
                game_id: gameId,
                row: row,
                col: col
            };
            console.log('Calling get_question_details AJAX with data:', data);
            $.get(ajaxUrl, data, function(response) {
                console.log('get_question_details response received:', response);
                if (response.success) {
                    if (response.data.type === 'question') {
                        console.log('Cell is a question. Showing modal.');
                        showQuestionModal(row, col, response.data.details);
                    } else {
                        console.log('Cell is not a question (' + response.data.type + '). Revealing immediately.');
                        revealCell(row, col);
                    }
                } else {
                    console.error('get_question_details failed:', response.data ? response.data.message : 'Unknown error.');
                    getGameState();
                }
            });
        }

        function showQuestionModal(row, col, details) {
            console.log('showQuestionModal() called.');
            var modalEl = $('#quiz-sweeper-modal');
            var choicesHtml = '';
            details.choices.forEach(function(choice, index) {
                if (choice) {
                    choicesHtml += `<label style="display: block; margin: 5px 0;"><input type="radio" name="answer" value="${index}" ${index === 0 ? 'checked' : ''}> ${choice}</label>`;
                }
            });

            var modalContent = `
                <div id="quiz-sweeper-modal-content">
                    <h3>${details.title}</h3>
                    <div id="question-answer-form">
                        ${choicesHtml}
                        <p style="margin-top: 15px;">
                            <button type="button" id="submit-answer-button" class="button button-primary">Submit Answer</button>
                        </p>
                    </div>
                </div>
            `;
            modalEl.html(modalContent).show();

            $('#submit-answer-button').on('click', function(e) {
                e.preventDefault();
                console.log('Submit Answer button clicked.');
                var answerIndex = $('#question-answer-form').find('input[name="answer"]:checked').val();
                revealCell(row, col, answerIndex);
                modalEl.hide().empty();
            });
        }

        function revealCell(row, col, answerIndex) {
            console.log('revealCell() called for cell:', row, col, 'with answerIndex:', answerIndex);
            var data = {
                action: 'reveal_cell',
                nonce: nonce,
                game_id: gameId,
                row: row,
                col: col,
                answer_index: answerIndex
            };
            console.log('Calling reveal_cell AJAX with data:', data);
            $.post(ajaxUrl, data, function(response) {
                console.log('reveal_cell response received:', response);
                if (response.success) {
                    console.log('reveal_cell success. Optimistically updating UI.');
                    var cellEl = boardEl.find('.cell[data-row="' + row + '"][data-col="' + col + '"]');
                    cellEl.off('click').addClass('revealed');
                    var icon = '';
                    switch(response.data.cell_type) {
                        case 'bomb': icon = '💣'; break;
                        case 'knife': icon = '🔪'; break;
                        case 'question':
                            if (response.data.was_correct) {
                                icon = '✅';
                                cellEl.addClass('correct');
                            } else {
                                icon = '❌';
                                cellEl.addClass('incorrect');
                            }
                            break;
                        case 'empty': icon = ' '; break;
                    }
                    cellEl.html('<span class="cell-icon">' + icon + '</span>');
                    console.log('Optimistic update complete. Fetching new game state for score update.');
                    getGameState();
                } else {
                    console.error('reveal_cell failed:', response.data ? response.data.message : 'Unknown error.');
                }
            });
        }

        function showGameOver(scores) {
            console.log('showGameOver() called.');
            var finalScoresHtml = '<h2>Game Over!</h2><h3>Final Scores:</h3><ul>';
            scores.forEach(function(scoreData) {
                finalScoresHtml += '<li>' + scoreData.group_name + ': ' + scoreData.score + '</li>';
            });
            finalScoresHtml += '</ul>';
            finalScoresHtml += '<a href="/" class="button">Exit to Homepage</a>';

            boardEl.off('click');
            boardEl.after(finalScoresHtml);
        }

        // Initial fetch
        getGameState();

        // Set up polling
        gameStateInterval = setInterval(getGameState, 5000);
    });

})( jQuery );
