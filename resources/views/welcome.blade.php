<form action="/api/login" method="POST">
    @csrf
    <input type="text" name="email" placeholder="Type your email>
    <input type="text" name="password" placeholder="Type your password>
    <button type="submit">Send</button>
</form>

<div id="ai-response">
    <!-- AI response will be displayed here -->
</div>
