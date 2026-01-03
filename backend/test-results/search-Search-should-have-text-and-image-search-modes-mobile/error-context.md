# Page snapshot

```yaml
- generic [ref=e4]:
  - generic [ref=e5]:
    - img "DanaVision" [ref=e6]
    - heading "DanaVision" [level=1] [ref=e7]
    - paragraph [ref=e8]: Smart Shopping for Dana
  - generic [ref=e9]:
    - generic [ref=e10]:
      - heading "Welcome back" [level=3] [ref=e11]
      - paragraph [ref=e12]: Sign in to your account
    - generic [ref=e13]:
      - generic [ref=e14]:
        - generic [ref=e15]:
          - text: Email
          - textbox "Email" [ref=e16]:
            - /placeholder: dana@example.com
        - generic [ref=e17]:
          - text: Password
          - generic [ref=e18]:
            - textbox "Password" [ref=e19]:
              - /placeholder: ••••••••
            - button "Show password" [ref=e20] [cursor=pointer]:
              - img [ref=e21]
        - generic [ref=e24]:
          - checkbox "Remember me" [ref=e25]
          - generic [ref=e26] [cursor=pointer]: Remember me
        - button "Sign In" [ref=e27] [cursor=pointer]
      - paragraph [ref=e28]:
        - text: Don't have an account?
        - link "Sign Up" [ref=e29]:
          - /url: /register
```