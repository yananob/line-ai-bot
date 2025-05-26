# Learnings from Implementing Web Search Feature

This document outlines the key learnings and observations from the task of integrating a web search capability into the existing PHP-based chatbot.

## Key Implementation Steps

The core of the task involved modifying the `PersonalBot` class to:
1.  **Decide if a web search is necessary**: A GPT prompt (`PROMPT_JUDGE_WEB_SEARCH`) was introduced to make this decision based on the user's message.
2.  **Perform the web search**: A new `WebSearchTool` class (currently with a placeholder implementation) was created to handle the actual search query.
3.  **Integrate search results into context**: The bot's GPT context (`GPT_CONTEXT`) was augmented to include a section for web search results, which are then populated if a search is performed.
4.  **Orchestrate the flow**: The `PersonalBot::getAnswer` method was updated to manage this new workflow: decide -> search (if needed) -> augment context -> generate answer.

## Challenges and Solutions

*   **Mocking Dependencies for Testing**:
    *   **Challenge**: `PersonalBot` relies on the `Gpt` class. Testing methods like `__shouldPerformWebSearch` and `getAnswer` required controlling the output of `Gpt` calls.
    *   **Solution**: PHPUnit's mocking capabilities (`createMock`, `method`, `willReturn`, `at`) were used. A helper method `setPrivateProperty` (added to `PersonalBotTest`) was necessary to inject the mocked `Gpt` instance into the `PersonalBot` instance under test, as the `$gpt` property was private and not set via the constructor in a way that allowed easy test-time replacement.

*   **Testing Private Methods**:
    *   **Challenge**: Some logic (like decision-making for search, context assembly) was encapsulated in private methods (`__shouldPerformWebSearch`, `__getContext`).
    *   **Solution**: The existing test utility `yananob\MyTools\Test::invokePrivateMethod` was leveraged to directly test these private methods, allowing for more focused unit tests.

*   **Context Management**:
    *   **Challenge**: Ensuring the GPT prompt context was correctly and dynamically assembled with optional sections (like web search results, user characteristics, conversation history) without errors.
    *   **Solution**: A placeholder system in the `GPT_CONTEXT` string (e.g., `<web_search_results>`) combined with `str_replace` and a helper method `__removeFromContext` for conditional removal of sections proved effective.

*   **Static vs. Instance Methods for Tools**:
    *   **Consideration**: `WebSearchTool::search()` was implemented as a static method. While simple for a placeholder, if it involved more complex dependencies or state, an instance method and dependency injection for `WebSearchTool` itself might have been preferable for easier mocking. For the current scope, direct static call was acceptable. The `PersonalBot` uses a fully qualified name `\MyApp\WebSearchTool::search()` which is clear.

## Unit Testing Highlights

*   Created `WebSearchToolTest.php` for the new tool.
*   Significantly updated `PersonalBotTest.php`:
    *   Added tests for `__shouldPerformWebSearch` (mocking GPT for decision).
    *   Added tests for `__getContext` (verifying inclusion/exclusion of search results).
    *   Added tests for `getAnswer` (mocking GPT for decision and final response, verifying context passed to GPT).
    *   Used `$this->at()` to specify sequences of mocked GPT calls within `getAnswer` tests.

## Future Considerations

*   **Real Web Search API Integration**: The `WebSearchTool::search()` is currently a placeholder. Integrating a real search API (e.g., Google Search API, Bing Search API, or a library like SerpAPI) would be the next step for full functionality. This would also involve API key management and potentially error handling for network issues or API limits.
*   **Refining Search Queries**: The user's message is directly used as a search query. NLP techniques could be applied to extract better search terms from the message.
*   **Summarizing Search Results**: Real search results can be verbose. A summarization step (possibly another GPT call or a different model) might be needed to make the information concise for the bot's context.
*   **Caching**: For identical or similar queries, caching search results could optimize performance and reduce API calls.
